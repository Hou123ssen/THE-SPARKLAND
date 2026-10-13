from pathlib import Path
import argparse

from PIL import Image
from rembg import new_session, remove


ROOT = Path(__file__).resolve().parents[1]
SOURCE_DIR = ROOT / "public" / "images" / "hero-sequence"


def process(output_dir, numbers):
    output_dir.mkdir(parents=True, exist_ok=True)
    session = new_session("u2net")

    for number in numbers:
        filename = f"ezgif-frame-{number:03d}.png"
        source_path = SOURCE_DIR / filename
        output_path = output_dir / filename
        with Image.open(source_path) as source:
            source.load()
            result = remove(
                source,
                session=session,
                alpha_matting=True,
                alpha_matting_foreground_threshold=240,
                alpha_matting_background_threshold=8,
                alpha_matting_erode_size=1,
            )
            result = result.convert("RGBA")
            if result.size != source.size:
                raise RuntimeError(f"Unexpected resize for {filename}: {result.size} != {source.size}")
            result.save(output_path, format="PNG", optimize=False)
            print(f"processed {filename} -> {output_path}")


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--full", action="store_true")
    args = parser.parse_args()
    numbers = range(1, 81) if args.full else (1, 20, 40, 60, 80)
    output_dir = ROOT / "public" / ("images/hero-sequence-transparent" if args.full else "images/hero-sequence-transparent-test")
    process(output_dir, numbers)


if __name__ == "__main__":
    main()
