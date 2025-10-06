<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Vista de cámara</title>
  <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
  <style>
    body {
      background-color: #111;
      color: #eee;
      text-align: center;
      font-family: Arial, sans-serif;
      padding-top: 40px;
    }
    video {
      width: 80%;
      max-width: 960px;
      border: 2px solid #444;
      border-radius: 8px;
      background: black;
    }
  </style>
</head>
<body>
  <h1>Vista de la cámara</h1>
  <video id="video" controls autoplay muted playsinline></video>
  <?php echo "test";?>
  <script>
    const video = document.getElementById('video');
    const src = '/hls/cam1.m3u8'; // cambia a tu stream
    if (Hls.isSupported()) {
      const hls = new Hls({ lowLatencyMode: true });
      hls.loadSource(src);
      hls.attachMedia(video);
    } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
      video.src = src; // Safari o iPhone
    } else {
      document.body.innerHTML += "<p>Tu navegador no soporta video HLS.</p>";
    }
  </script>
</body>
</html>


