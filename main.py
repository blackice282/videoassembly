from audio_utils import select_audio_file, sync_audio_video

music_dir = 'Musica'
video_path = 'input_video.mp4'
output_path = 'output_video.mp4'

audio_file = select_audio_file(music_dir, criteria='longest')
if audio_file:
    sync_audio_video(video_path, f"{music_dir}/{audio_file}", output_path)
    print("Montaggio completato!")
else:
    print("Nessun file audio trovato nella cartella Musica.")
