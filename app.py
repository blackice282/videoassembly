from flask import Flask, request, jsonify, send_file
import os
import uuid
from face_privacy import apply_smile_to_faces

app = Flask(__name__)
UPLOAD_FOLDER = "uploads"
PROCESSED_FOLDER = "processed"
EMOJI_PATH = "faccia felice.png"

os.makedirs(UPLOAD_FOLDER, exist_ok=True)
os.makedirs(PROCESSED_FOLDER, exist_ok=True)

@app.route("/process", methods=["POST"])
def process_video():
    if "video" not in request.files:
        return jsonify({"error": "Missing video file"}), 400

    video_file = request.files["video"]
    video_id = str(uuid.uuid4())
    input_path = os.path.join(UPLOAD_FOLDER, f"{video_id}.mp4")
    output_path = os.path.join(PROCESSED_FOLDER, f"{video_id}_out.mp4")

    video_file.save(input_path)

    success = apply_smile_to_faces(input_path, output_path, EMOJI_PATH)

    if not success:
        return jsonify({"error": "Processing failed"}), 500

    return send_file(output_path, mimetype="video/mp4")

if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5001)
