"""Read-only inventory of original and web-ready ACULTA branding assets."""
from pathlib import Path
import struct
import zlib

try:
    from PIL import Image
except ImportError:
    print("Pillow is unavailable; PNG headers and ICO directory entries only.")
    Image = None

root = Path("web/themes/custom/aculta/assets/branding/aculta")
for path in sorted(p for p in root.rglob("*") if p.is_file()):
    data = path.read_bytes()
    if path.suffix.lower() == ".ico" and data[:4] == b"\x00\x00\x01\x00":
        _, _, count = struct.unpack_from("<HHH", data, 0)
        sizes = []
        for offset in range(6, 6 + count * 16, 16):
            width, height, _, _, _, bpp, _, _ = struct.unpack_from("<BBBBHHII", data, offset)
            sizes.append(f"{width or 256}x{height or 256}@{bpp}bit")
        print(f"{path.as_posix()} | ICO | {', '.join(sizes)} | embedded images; source preserved")
    elif path.suffix.lower() == ".png" and data[:8] == b"\x89PNG\r\n\x1a\n":
        width, height = struct.unpack_from(">II", data, 16)
        bit_depth, color_type = data[24], data[25]
        alpha = color_type in (4, 6)
        transparency_chunk = b"tRNS" in data
        transparent = "unknown (alpha channel)" if alpha else "yes" if transparency_chunk else "no"
        if alpha and Image is not None:
            with Image.open(path) as image:
                alpha_min, _ = image.convert("RGBA").getchannel("A").getextrema()
                transparent = "yes" if alpha_min < 255 else "no"
        elif alpha and bit_depth == 8 and color_type == 6:
            offset = 8
            compressed = bytearray()
            while offset < len(data):
                length = struct.unpack_from(">I", data, offset)[0]
                kind = data[offset + 4:offset + 8]
                payload = data[offset + 8:offset + 8 + length]
                if kind == b"IDAT":
                    compressed.extend(payload)
                offset += 12 + length
                if kind == b"IEND":
                    break
            pixels = zlib.decompress(compressed)
            stride = width * 4
            previous = bytearray(stride)
            alpha_values = []
            pos = 0
            for _ in range(height):
                filter_type = pixels[pos]
                row = bytearray(pixels[pos + 1:pos + 1 + stride])
                pos += stride + 1
                for i in range(stride):
                    left = row[i - 4] if i >= 4 else 0
                    up = previous[i]
                    upper_left = previous[i - 4] if i >= 4 else 0
                    if filter_type == 1:
                        predictor = left
                    elif filter_type == 2:
                        predictor = up
                    elif filter_type == 3:
                        predictor = (left + up) // 2
                    elif filter_type == 4:
                        p = left + up - upper_left
                        pa, pb, pc = abs(p - left), abs(p - up), abs(p - upper_left)
                        predictor = left if pa <= pb and pa <= pc else up if pb <= pc else upper_left
                    elif filter_type == 0:
                        predictor = 0
                    else:
                        raise ValueError(f"Unsupported PNG filter {filter_type}: {path}")
                    row[i] = (row[i] + predictor) & 255
                alpha_values.extend(row[3::4])
                previous = row
            transparent = "yes" if min(alpha_values, default=255) < 255 else "no"
        print(f"{path.as_posix()} | PNG | {width}x{height} | ratio={width / height:.4f} | transparency={transparent}")
    else:
        print(f"{path.as_posix()} | unknown format | {len(data)} bytes")
