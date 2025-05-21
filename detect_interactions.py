import sys
import shutil
import os

if len(sys.argv) != 3:
    print("Usage: detect_interactions.py input.mp4 output.mp4")
    sys.exit(1)

input_path = sys.argv[1]
output_path = sys.argv[2]

print(f"Input: {input_path}")
print(f"Output: {output_path}")
print(f"Input exists: {os.path.exists(input_path)}")

try:
    shutil.copy(input_path, output_path)
    print("Copy OK")
except Exception as e:
    print(f"Copy failed: {e}")