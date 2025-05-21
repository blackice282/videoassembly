# Video Assembly Project

## Overview
The Video Assembly project is designed to process video files by detecting segments with people, applying transitions, and generating output videos. It utilizes FFmpeg for video processing and provides a user-friendly interface for uploading and managing video files.

## Features
- Upload video files for processing.
- Detect segments with people based on configurable parameters.
- Apply transitions between segments.
- Output videos in specified resolutions and formats.

## Setup Instructions
1. **Clone the repository:**
   ```
   git clone https://your-repo-url.git
   cd videoassembly
   ```

2. **Install dependencies:**
   Ensure you have Composer installed, then run:
   ```
   composer install
   ```

3. **Configure the application:**
   Edit the `config.php` file to set your desired configurations, including paths, FFmpeg settings, and system options.

4. **Run the application:**
   Start the application using your preferred PHP server. For example:
   ```
   php -S localhost:8000 -t src
   ```

## Usage
- Access the application via your web browser at `http://localhost:8000`.
- Upload video files through the provided interface.
- Configure detection and transition settings as needed.
- Process the videos and download the output.

## Contributing
Contributions are welcome! Please submit a pull request or open an issue for any enhancements or bug fixes.

## License
This project is licensed under the MIT License. See the LICENSE file for more details.