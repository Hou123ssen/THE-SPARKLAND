from pathlib import Path
import json

import numpy as np
from PIL import Image
from scipy import ndimage


ROOT = Path(__file__).resolve().parents[1]
SOURCE_DIR = ROOT / "public" / "images" / "hero-sequence"
OUTPUT_DIR = ROOT / "public" / "images" / "hero-sequence-white-test"
FRAME_NUMBERS = (1, 20, 40, 60, 80)
CORE_DISTANCE = 30.0
FEATHER_DISTANCE = 52.0


def process(source):
    rgb = np.asarray(source.convert("RGB"), dtype=np.float32)
    height, width = rgb.shape[:2]
    step = max(1, min(width, height) // 180)
    inset = min(6, width // 20, height // 20)

    border_samples = np.concatenate(
        (
            rgb[inset, ::step],
            rgb[height - inset - 1, ::step],
            rgb[::step, inset],
            rgb[::step, width - inset - 1],
        ),
        axis=0,
    )
    background = np.median(border_samples, axis=0)
    distances = np.sqrt(np.sum((rgb - background) ** 2, axis=2))

    # Only a border-connected region is eligible for normalization. This
    # protects bright skin, reflections, and highlights inside the subject.
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

    # Core background becomes exact white. The narrow boundary band blends
    # toward white without altering pixels outside the connected matte.
    result = rgb.copy()
    core = connected & (distances <= CORE_DISTANCE)
    result[core] = 255.0
    feather = connected & ~core
    ratio = np.clip((distances - CORE_DISTANCE) / (FEATHER_DISTANCE - CORE_DISTANCE), 0.0, 1.0)
    smooth = ratio * ratio * (3.0 - 2.0 * ratio)
    result[feather] = result[feather] * (1.0 - smooth[feather, None]) + 255.0 * smooth[feather, None]

    output = Image.fromarray(np.clip(result, 0, 255).astype(np.uint8), mode="RGB")
    return output, background, int(np.count_nonzero(core)), int(np.count_nonzero(feather))


def main():
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
    report = {"method": "border-connected studio background normalization", "frames": []}

    for number in FRAME_NUMBERS:
        filename = f"ezgif-frame-{number:03d}.png"
        source_path = SOURCE_DIR / filename
        output_path = OUTPUT_DIR / filename
        with Image.open(source_path) as source:
            source.load()
            output, background, core_pixels, feather_pixels = process(source)
            output.save(output_path, format="PNG", optimize=False)
            report["frames"].append(
                {
                    "frame": number,
                    "source": str(source_path.relative_to(ROOT)),
                    "output": str(output_path.relative_to(ROOT)),
                    "size": list(source.size),
                    "mode": output.mode,
                    "estimated_background_rgb": [int(round(value)) for value in background],
                    "normalized_core_pixels": core_pixels,
                    "soft_boundary_pixels": feather_pixels,
                }
            )

    (OUTPUT_DIR / "white-background-report.json").write_text(json.dumps(report, indent=2), encoding="utf-8")
    print(json.dumps(report, indent=2))


if __name__ == "__main__":
    main()
