from pathlib import Path
from statistics import median
import json
import math

import numpy as np
from PIL import Image, ImageFilter
from scipy import ndimage


ROOT = Path(__file__).resolve().parents[1]
SOURCE_DIR = ROOT / "public" / "images" / "hero-sequence"
OUTPUT_DIR = ROOT / "public" / "images" / "background-test"
MASK_DIR = OUTPUT_DIR / "masks"
FRAME_NUMBERS = (1, 20, 40, 60, 80)

# These are deliberately conservative. The connected flood fill is the guard
# against removing bright skin, lens reflections, or clothing highlights.
CORE_DISTANCE = 24.0
FEATHER_DISTANCE = 48.0
NEIGHBOURS = ((1, 0), (-1, 0), (0, 1), (0, -1))


def rgb_distance(pixel, background):
    return math.sqrt(sum((pixel[index] - background[index]) ** 2 for index in range(3)))


def border_samples(pixels, width, height):
    points = set()
    step = max(1, min(width, height) // 180)
    inset = min(6, width // 20, height // 20)

    for x in range(0, width, step):
        points.add((x, inset))
        points.add((x, height - inset - 1))
    for y in range(0, height, step):
        points.add((inset, y))
        points.add((width - inset - 1, y))

    return [pixels[y * width + x] for x, y in points]


def estimate_background(pixels, width, height):
    samples = border_samples(pixels, width, height)
    # A component-wise median is robust to a small amount of subject touching
    # the frame edge and avoids a global brightness key.
    background = tuple(int(round(median(channel))) for channel in zip(*samples))
    return background, samples


def make_matte(image):
    width, height = image.size
    rgb = np.asarray(image.convert("RGB"), dtype=np.float32)
    step = max(1, min(width, height) // 180)
    inset = min(6, width // 20, height // 20)
    samples_array = np.concatenate(
        (
            rgb[inset, ::step],
            rgb[height - inset - 1, ::step],
            rgb[::step, inset],
            rgb[::step, width - inset - 1],
        ),
        axis=0,
    )
    background = tuple(int(round(value)) for value in np.median(samples_array, axis=0))
    samples = [tuple(pixel.astype(int)) for pixel in samples_array]
    background_array = np.asarray(background, dtype=np.float32)
    distances = np.sqrt(np.sum((rgb - background_array) ** 2, axis=2))

    # Only pixels near the sampled background are candidates. Propagating from
    # the outer border means bright subject pixels remain protected even when
    # their color is close to the studio background.
    candidate = distances <= FEATHER_DISTANCE
    seeds = np.zeros((height, width), dtype=bool)
    seeds[0, :] = candidate[0, :]
    seeds[-1, :] = candidate[-1, :]
    seeds[:, 0] = candidate[:, 0]
    seeds[:, -1] = candidate[:, -1]
    connected = ndimage.binary_propagation(
        seeds,
        structure=np.array([[0, 1, 0], [1, 1, 1], [0, 1, 0]], dtype=bool),
        mask=candidate,
    )

    alpha = np.full((height, width), 255.0, dtype=np.float32)
    core = connected & (distances <= CORE_DISTANCE)
    feather = connected & ~core
    ratio = np.clip((distances - CORE_DISTANCE) / (FEATHER_DISTANCE - CORE_DISTANCE), 0.0, 1.0)
    smooth = ratio * ratio * (3.0 - 2.0 * ratio)
    alpha[core] = 0.0
    alpha[feather] = smooth[feather] * 255.0
    alpha = ndimage.gaussian_filter(alpha, sigma=0.55)
    alpha = np.clip(alpha, 0.0, 255.0).astype(np.uint8)

    matte = Image.fromarray(alpha, mode="L")
    rgba = image.convert("RGBA")
    rgba.putalpha(matte)
    return rgba, matte, background, samples, int(np.count_nonzero(alpha == 0)), int(np.count_nonzero((alpha > 0) & (alpha < 255)))


def main():
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
    MASK_DIR.mkdir(parents=True, exist_ok=True)
    report = {"method": "border-connected background matte", "frames": []}

    for frame_number in FRAME_NUMBERS:
        filename = f"ezgif-frame-{frame_number:03d}.png"
        source_path = SOURCE_DIR / filename
        output_path = OUTPUT_DIR / filename
        mask_path = MASK_DIR / filename

        with Image.open(source_path) as source:
            source.load()
            output, matte, background, samples, transparent_pixels, feather_pixels = make_matte(source)
            output.save(output_path, format="PNG", optimize=False)
            matte.save(mask_path, format="PNG", optimize=False)

            report["frames"].append(
                {
                    "frame": frame_number,
                    "source": str(source_path.relative_to(ROOT)),
                    "output": str(output_path.relative_to(ROOT)),
                    "mask": str(mask_path.relative_to(ROOT)),
                    "size": list(source.size),
                    "mode": output.mode,
                    "estimated_background_rgb": list(background),
                    "border_sample_count": len(samples),
                    "transparent_pixel_count": transparent_pixels,
                    "feather_pixel_count": feather_pixels,
                }
            )

    (OUTPUT_DIR / "background-report.json").write_text(json.dumps(report, indent=2), encoding="utf-8")
    print(json.dumps(report, indent=2))


if __name__ == "__main__":
    main()
