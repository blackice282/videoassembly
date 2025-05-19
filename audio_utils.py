import os
from pydub import AudioSegment
from pydub.utils import mediainfo
from moviepy.editor import VideoFileClip, AudioFileClip, vfx

def get_audio_files(music_dir='Musica'):
    """Restituisce la lista dei file audio disponibili nella directory Musica."""
    supported_formats = ('.mp3', '.wav', '.aac', '.flac', '.ogg')
    return [f for f in os.listdir(music_dir) if f.lower().endswith(supported_formats)]

def select_audio_file(music_dir='Musica', criteria='longest'):
    """Seleziona un file audio in base al criterio scelto."""
    files = get_audio_files(music_dir)
    if not files:
        return None
    if criteria == 'alphabetical':
        return sorted(files)[0]
    elif criteria == 'longest':
        durations = []
        for f in files:
            info = mediainfo(os.path.join(music_dir, f))
            durations.append((float(info['duration']), f))
        return max(durations)[1]
    else:
        return files[0]

def sync_audio_video(video_path, audio_path, output_path):
    """Sincronizza automaticamente audio e video in base alla durata."""
    video = VideoFileClip(video_path)
    audio = AudioFileClip(audio_path)
    # Taglia o ripeti l'audio per adattarlo alla durata del video
    if audio.duration > video.duration:
        audio = audio.subclip(0, video.duration)
    else:
        audio = audio.fx(vfx.loop, duration=video.duration)
    final = video.set_audio(audio)
    final.write_videofile(output_path, codec='libx264', audio_codec='aac')

def crossfade_audios(audio1_path, audio2_path, output_path, crossfade_duration=2000):
    """Applica un crossfade tra due tracce audio."""
    audio1 = AudioSegment.from_file(audio1_path)
    audio2 = AudioSegment.from_file(audio2_path)
    combined = audio1.append(audio2, crossfade=crossfade_duration)
    combined.export(output_path, format="mp3")
