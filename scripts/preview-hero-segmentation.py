from pathlib import Path

from PIL import Image
from rembg import new_session, remove


ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / "public" / "images" / "hero-sequence"
PREVIEW = ROOT / "public" / "images" / "hero-sequence-clean-preview"
SAMPLE_FRAMES = (1, 25, 45, 65, 80)


def main():
    PREVIEW.mkdir(parents=True, exist_ok=True)
    session = new_session("birefnet-general-lite")

    for frame_number in SAMPLE_FRAMES:
        filename = f"ezgif-frame-{frame_number:03d}.png"
        source_path = SOURCE / filename
        output_path = PREVIEW / filename
        source = source_path.read_bytes()
        result = remove(
            source,
            session=session,
            alpha_matting=True,
            alpha_matting_foreground_threshold=240,
            alpha_matting_background_threshold=10,
            alpha_matting_erode_size=6,
        )
        output_path.write_bytes(result)
        with Image.open(output_path) as image:
            print(f"{output_path}: {image.mode} {image.size}")


if __name__ == "__main__":
    main()
