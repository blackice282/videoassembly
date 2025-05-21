import sys
import shutil

if len(sys.argv) != 3:
    print("Usage: detect_best_scenes.py input.mp4 output.mp4")
    sys.exit(1)

input_path = sys.argv[1]
output_path = sys.argv[2]

# Placeholder: copia semplicemente il file di input in output
shutil.copy(input_path, output_path)