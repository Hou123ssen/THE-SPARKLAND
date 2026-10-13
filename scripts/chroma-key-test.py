from collections import Counter
from pathlib import Path
from statistics import median
from colorsys import rgb_to_hsv
import json
import math

from PIL import Image, ImageFilter


ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / "public" / "images" / "hero-sequence"
OUTPUT = ROOT / "public" / "images" / "chroma-test"
MASKS = OUTPUT / "masks"
SAMPLE_FRAMES = (1, 20, 40, 60, 80)


def hsv(pixel):
    red, green, blue = (channel / 255 for channel in pixel[:3])
    return rgb_to_hsv(red, green, blue)


def border_samples(image):
    width, height = image.size
    pixels = image.load()
    points = set()
    border = 6
    for y in range(border, height - border, 4):
        for x in range(border):
            points.add((x, y))
            points.add((width - 1 - x, y))
    for x in range(border, width - border, 4):
        for y in range(border):
            points.add((x, y))
            points.add((x, height - 1 - y))
    return [hsv(pixels[x, y]) for x, y in points]


def dominant_background(samples):
    green_samples = [sample for sample in samples if sample[1] >= 0.2]
    if not green_samples:
        raise RuntimeError("Could not find a saturated background cluster on the frame border")

    bins = Counter(int(sample[0] * 50) % 50 for sample in green_samples)
    dominant_bin, _ = bins.most_common(1)[0]
    cluster = [sample for sample in green_samples if int(sample[0] * 50) % 50 == dominant_bin]
    return tuple(median(sample[index] for sample in cluster) for index in range(3))


def hue_distance(first, second):
    distance = abs(first - second)
    return min(distance, 1 - distance)


def smoothstep(edge_start, edge_end, value):
    amount = max(0.0, min(1.0, (value - edge_start) / (edge_end - edge_start)))
    return amount * amount * (3 - 2 * amount)


def process_frame(frame_number):
    filename = f"ezgif-frame-{frame_number:03d}.png"
    source_path = SOURCE / filename
    output_path = OUTPUT / filename
    mask_path = MASKS / filename
    image = Image.open(source_path).convert("RGB")
    pixels = image.load()
    background = dominant_background(border_samples(image))
    width, height = image.size
    alpha = Image.new("L", image.size, 0)
    alpha_pixels = alpha.load()
    output_pixels = image.load()

    for y in range(height):
        for x in range(width):
            red, green, blue = output_pixels[x, y]
            hue, saturation, value = hsv((red, green, blue))
            hue_delta = hue_distance(hue, background[0])
            saturation_delta = abs(saturation - background[1])
            value_delta = abs(value - background[2])
            distance = math.sqrt(
                (hue_delta / 0.085) ** 2
                + (saturation_delta / 0.28) ** 2
                + (value_delta / 0.30) ** 2
            )
            # Saturated background-green pixels fade out; dark or hue-shifted subject pixels stay.
            if saturation < 0.16 or value < 0.13:
                opacity = 1.0
            else:
                opacity = smoothstep(0.62, 1.18, distance)
            alpha_value = round(opacity * 255)
            alpha_pixels[x, y] = alpha_value

    alpha = alpha.filter(ImageFilter.GaussianBlur(radius=0.65))
    alpha_pixels = alpha.load()

    # Despill the keyed edge band, including opaque pixels touching that band.
    # Interior lens reflections and product colors have no transparent neighbor and stay intact.
    for y in range(1, height - 1):
        for x in range(1, width - 1):
            alpha_value = alpha_pixels[x, y]
            neighboring_alpha = min(
                alpha_pixels[x - 1, y],
                alpha_pixels[x + 1, y],
                alpha_pixels[x, y - 1],
                alpha_pixels[x, y + 1],
            )
            red, green, blue = output_pixels[x, y]
            hue, saturation, _ = hsv((red, green, blue))
            is_edge = alpha_value < 255 or neighboring_alpha < 235
            has_green_spill = green > max(red, blue) + 8
            if is_edge and has_green_spill and hue_distance(hue, background[0]) < 0.13:
                edge_strength = 0.38 if alpha_value == 255 else 0.65
                target_green = min(green, max(red, blue) + 6)
                output_pixels[x, y] = (
                    red,
                    round(green * (1 - edge_strength) + target_green * edge_strength),
                    blue,
                )

    alpha.save(mask_path)
    result = Image.merge("RGBA", (*output_pixels_to_channels(image), alpha))
    result.save(output_path)
    return {
        "frame": filename,
        "background_hsv": {
            "hue_degrees": round(background[0] * 360, 2),
            "saturation": round(background[1], 4),
            "value": round(background[2], 4),
        },
        "key_tolerance": "HSV distance feather 0.62-1.18; hue scale 0.085, saturation scale 0.28, value scale 0.30",
        "feather": "0.65px Gaussian alpha smoothing",
    }


def output_pixels_to_channels(image):
    channels = image.split()
    return channels[0], channels[1], channels[2]


def main():
    OUTPUT.mkdir(parents=True, exist_ok=True)
    MASKS.mkdir(parents=True, exist_ok=True)
    report = [process_frame(frame_number) for frame_number in SAMPLE_FRAMES]
    (OUTPUT / "chroma-report.json").write_text(json.dumps(report, indent=2), encoding="utf-8")
    print(json.dumps(report, indent=2))


if __name__ == "__main__":
    main()
