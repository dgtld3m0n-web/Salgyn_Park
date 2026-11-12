#!/usr/bin/env python3
"""
Перевод фото в WebP и видео в MP4/WebM для сайта «Салгын Парк».

Как пользоваться:
  1. Положи присланные фото и видео в папку  incoming/
     Называй файлы так, как их ждёт сайт (см. README): например  aerotruba.jpg, aerotruba-2.jpg, hero.jpg
  2. Запусти:  python convert_media.py
  3. Готовые файлы появятся в images/ (фото) и images/video/ (видео). Коммить их в репозиторий.

Нужно:  pip install pillow   (для видео ещё установленный ffmpeg; для .heic с iPhone: pip install pillow-heif)
"""
import shutil, subprocess, sys
from pathlib import Path
from PIL import Image, ImageOps

try:
    import pillow_heif; pillow_heif.register_heif_opener()
except ImportError:
    pass

SRC, OUT = Path('incoming'), Path('images')
MAX_W, QUALITY = 1600, 82                      # ширина не больше 1600 px, качество 82
PHOTO = {'.jpg', '.jpeg', '.png', '.heic', '.bmp', '.tiff', '.webp'}
VIDEO = {'.mp4', '.mov', '.avi', '.mkv', '.webm', '.m4v'}

# где лежит файл в итоге: по имени (slug)
SERVICES = {'aerotruba', 'batut', 'labyrinth', 'vr-arena', 'ps5', 'arcade', 'spiderman', 'vr-stand', 'certificate'}
VIP = {'zemlya', 'voda', 'vozduh', 'ogon', 'metall'}

def target_dir(stem):
    base = stem[:-2] if stem.endswith('-2') else stem
    return OUT / 'services' if base in SERVICES else OUT / 'vip' if base in VIP else OUT

def photo(f):
    dst = target_dir(f.stem.lower()) / (f.stem.lower() + '.webp')
    dst.parent.mkdir(parents=True, exist_ok=True)
    im = ImageOps.exif_transpose(Image.open(f))       # поворот по метаданным телефона
    if im.width > MAX_W:
        im = im.resize((MAX_W, round(im.height * MAX_W / im.width)), Image.LANCZOS)
    im.convert('RGBA' if im.mode in ('RGBA', 'LA', 'P') else 'RGB').save(dst, 'WEBP', quality=QUALITY, method=6)
    print(f'фото  {f.name} -> {dst}  ({f.stat().st_size // 1024} КБ -> {dst.stat().st_size // 1024} КБ)')

def video(f):
    if not shutil.which('ffmpeg'):
        print(f'видео {f.name}: пропущено, не найден ffmpeg (https://ffmpeg.org)'); return
    d = OUT / 'video'; d.mkdir(parents=True, exist_ok=True)
    s = f.stem.lower()
    run = lambda *a: subprocess.run(['ffmpeg', '-y', '-loglevel', 'error', '-i', str(f), *a], check=True)
    run('-vf', 'scale=-2:min(ih\\,1080)', '-c:v', 'libx264', '-crf', '24', '-preset', 'slow', '-pix_fmt', 'yuv420p',
        '-movflags', '+faststart', '-an', str(d / f'{s}.mp4'))                  # основной формат, играет везде
    run('-vf', 'scale=-2:min(ih\\,1080)', '-c:v', 'libvpx-vp9', '-crf', '34', '-b:v', '0', '-an', str(d / f'{s}.webm'))
    run('-frames:v', '1', '-vf', 'scale=1280:-2', str(d / f'{s}-poster.webp'))    # кадр-обложка
    print(f'видео {f.name} -> {d}/{s}.mp4, .webm, -poster.webp')

if not SRC.exists():
    sys.exit('Нет папки incoming/. Создай её и положи туда фото и видео.')
files = [f for f in sorted(SRC.iterdir()) if f.is_file() and not f.name.startswith('.')]
for f in files:
    ext = f.suffix.lower()
    if ext in PHOTO: photo(f)
    elif ext in VIDEO: video(f)
    else: print(f'{f.name}: формат {ext} не знаю, пропущено')
print('Готово.' if files else 'В incoming/ пока пусто.')
