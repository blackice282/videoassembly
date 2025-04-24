import cv2
import numpy as np
import os

def apply_smile_to_faces(input_video, output_video, smile_png_path):
    face_cascade = cv2.CascadeClassifier(cv2.data.haarcascades + 'haarcascade_frontalface_default.xml')
    smile_overlay = cv2.imread(smile_png_path, cv2.IMREAD_UNCHANGED)  # PNG con canale alpha

    cap = cv2.VideoCapture(input_video)
    if not cap.isOpened():
        print("Errore nell'apertura del video.")
        return False

    width  = int(cap.get(cv2.CAP_PROP_FRAME_WIDTH))
    height = int(cap.get(cv2.CAP_PROP_FRAME_HEIGHT))
    fps    = cap.get(cv2.CAP_PROP_FPS)

    fourcc = cv2.VideoWriter_fourcc(*'mp4v')
    out = cv2.VideoWriter(output_video, fourcc, fps, (width, height))

    while True:
        ret, frame = cap.read()
        if not ret:
            break

        hsv = cv2.cvtColor(frame, cv2.COLOR_BGR2HSV)
        mask_yellow = cv2.inRange(hsv, (20, 100, 100), (30, 255, 255))  # intervallo giallo
        contours, _ = cv2.findContours(mask_yellow, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
        yellow_areas = [cv2.boundingRect(c) for c in contours if cv2.contourArea(c) > 500]

        gray = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)
        faces = face_cascade.detectMultiScale(gray, 1.1, 4)

        for (x, y, w, h) in faces:
            face_center = (x + w//2, y + h//2)
            is_yellow_near = any(abs(x - xa) < w and abs(y - ya) < h for (xa, ya, wa, ha) in yellow_areas)

            if not is_yellow_near:
                resized_smile = cv2.resize(smile_overlay, (w, h))
                overlay_image_alpha(frame, resized_smile[:, :, 0:3], (x, y), resized_smile[:, :, 3] / 255.0)

        out.write(frame)

    cap.release()
    out.release()
    return True

def overlay_image_alpha(img, img_overlay, pos, alpha_mask):
    x, y = pos
    h, w = img_overlay.shape[0], img_overlay.shape[1]

    if y + h > img.shape[0] or x + w > img.shape[1]:
        return  # ignora overlay fuori schermo

    for c in range(3):
        img[y:y+h, x:x+w, c] = (1. - alpha_mask) * img[y:y+h, x:x+w, c] + alpha_mask * img_overlay[:, :, c]
