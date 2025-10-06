<?php
declare(strict_types=1);

// -----------------------------------------------------------------------------
// Este archivo genera una página de visualización de la estadía de un cliente
// de IziPark. La intención es ofrecer una experiencia cuidada y completa para
// quienes alquilan temporalmente la cochera del anfitrión. El visitante
// accede a esta página escaneando un QR o introduciendo un token único en la
// URL. Si la estadía está activa, se muestra la transmisión en vivo de la
// cámara junto con datos de inicio, fin, tiempo restante y un indicador
// progresivo. Si la estadía no está activa, se informa al usuario sin
// exponer detalles sensibles.
//
// Para utilizar este script, asegúrate de que exista una función
// `obtenerEstadia(string $token): array` en funciones.php que devuelva un
// arreglo asociativo con las claves 'inicio', 'fin', etc., tal como se
// ejemplifica más abajo.
// -----------------------------------------------------------------------------

// Configura la zona horaria correspondiente a la cochera (Buenos Aires)
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Obtiene y valida el token recibido por GET. Se permite una longitud entre 4 y 64
// caracteres compuesta por letras, números, guiones y guiones bajos.
$rawToken = $_GET['token'] ?? '';
if (!preg_match('/^[A-Za-z0-9_-]{4,64}$/', $rawToken)) {
    http_response_code(400);
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Error</title></head><body style="background:#111;color:#eee;font-family:sans-serif;text-align:center;padding-top:40px;">';
    echo '<h1>Token inválido</h1><p>Por favor, verifica tu enlace o consulta con el anfitrión.</p></body></html>';
    exit;
}

// Incluye las funciones de negocio (debe contener obtenerEstadia)
@include_once __DIR__ . '/funciones.php';

// Recupera la estadía asociada al token. Se espera un array con al menos una
// fila como esta:
// [
//   'id'       => int,
//   'idCliente'=> int,
//   'inicio'   => 'YYYY-mm-dd HH:ii:ss',
//   'fin'      => 'YYYY-mm-dd HH:ii:ss',
//   'token'    => '...'
// ]
$rows = function_exists('obtenerEstadia') ? obtenerEstadia($rawToken) : [];
$estadia = (is_array($rows) && isset($rows[0])) ? $rows[0] : null;

if (!$estadia) {
    // Si no hay estadía asociada, se muestra un mensaje agradable sin exponer
    // detalles técnicos.
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Sin estadía</title><style>body{background:#111;color:#eee;font-family:sans-serif;text-align:center;padding-top:40px;}a{color:#58a6ff;text-decoration:none;}a:hover{text-decoration:underline;}</style></head><body>';
    echo '<h1>No se encontró una estadía asociada</h1>';
    echo '<p>El enlace o token que has utilizado no corresponde a una estadía activa.</p>';
    echo '<p>Si crees que se trata de un error, ponte en contacto con tu anfitrión.</p>';
    echo '</body></html>';
    exit;
}

// Intenta crear objetos DateTime para las fechas. Si hay un error en las
// cadenas de fecha, se considera que la estadía no es válida.
try {
    $inicio = new DateTime($estadia['inicio']);
    $fin    = new DateTime($estadia['fin']);
    $now    = new DateTime('now');
} catch (Exception $e) {
    http_response_code(500);
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Error</title></head><body style="background:#111;color:#eee;font-family:sans-serif;text-align:center;padding-top:40px;"><h1>Error al procesar la estadía</h1><p>Hubo un problema al interpretar las fechas de la estadía.</p></body></html>';
    exit;
}

// Determina si la estadía está activa en este momento
$activa = ($now >= $inicio && $now <= $fin);

// Calcula segundos totales y restantes para la barra de progreso y el contador
$totalSeconds = max(1, $fin->getTimestamp() - $inicio->getTimestamp());
$remainingSeconds = $activa ? max(0, $fin->getTimestamp() - $now->getTimestamp()) : 0;
$elapsedSeconds   = $activa ? max(0, $now->getTimestamp() - $inicio->getTimestamp()) : $totalSeconds;
$initialProgress  = (int)round(min(100, max(0, $elapsedSeconds / $totalSeconds * 100)));

// Se extrae el token solo para mostrar los últimos caracteres, si se desea.
$tokenDisplay = substr($estadia['token'], -4);

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Tu estadía en IziPark</title>
  <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
  <style>
    /* Modo oscuro global */
    :root { color-scheme: dark; }
    body {
      background-color: #0f0f0f;
      color: #eee;
      font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      margin: 0;
      padding: 40px 16px;
    }
    .container {
      max-width: 960px;
      margin: 0 auto;
      background: #1a1a1a;
      border-radius: 12px;
      padding: 24px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
    }
    header {
      text-align: center;
      margin-bottom: 24px;
    }
    header h1 {
      margin: 0;
      font-size: 1.8rem;
      font-weight: 700;
    }
    header p {
      margin: 8px 0 0;
      font-size: 0.9rem;
      color: #aaa;
    }
    .details {
      display: flex;
      flex-direction: column;
      gap: 8px;
      margin-bottom: 20px;
      font-size: 0.95rem;
    }
    .row {
      display: flex;
      justify-content: flex-start;
      flex-wrap: wrap;
      align-items: baseline;
      gap: 8px;
    }
    .label {
      font-weight: 600;
      color: #a0a0a0;
    }
    .value {
      color: #e5e5e5;
      font-weight: 500;
    }
    .badge {
      display: inline-block;
      padding: 4px 10px;
      border-radius: 12px;
      font-weight: 600;
      font-size: 0.85rem;
    }
    .badge.active {
      background-color: #17351c;
      color: #9effa9;
      border: 1px solid #1f5b28;
    }
    .badge.inactive {
      background-color: #3a1414;
      color: #ff9e9e;
      border: 1px solid #5b1f1f;
    }
    .progress-bar {
      width: 100%;
      height: 10px;
      background: #333;
      border-radius: 5px;
      overflow: hidden;
      margin-top: 6px;
    }
    .progress-bar-inner {
      height: 100%;
      background: linear-gradient(90deg, #2e7d32, #66bb6a);
      width: <?= $initialProgress ?>%;
      transition: width 0.5s linear;
    }
    .video-container {
      position: relative;
      margin-top: 16px;
    }
    video {
      width: 100%;
      max-width: 100%;
      border: 2px solid #333;
      border-radius: 12px;
      background: black;
    }
    .message {
      background: #2a2a2a;
      padding: 16px;
      border-radius: 8px;
      border: 1px solid #3f3f3f;
      margin-top: 20px;
      text-align: center;
      font-size: 0.95rem;
    }
    .message strong {
      color: #58a6ff;
    }
    /* Responsive adjustments */
    @media (max-width: 600px) {
      header h1 {
        font-size: 1.4rem;
      }
      .details {
        font-size: 0.9rem;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <header>
      <h1>¡Hola!</h1>
      <p>Gracias por confiar en IziPark y JI878</p>
    </header>

    <div class="details">
      <div class="row">
        <span class="label">Inicio:</span>
        <span class="value"><?= htmlspecialchars($inicio->format('d/m/Y H:i:s')) ?> (GMT-3)</span>
      </div>
      <div class="row">
        <span class="label">Fin:</span>
        <span class="value"><?= htmlspecialchars($fin->format('d/m/Y H:i:s')) ?> (GMT-3)</span>
      </div>
      <div class="row">
        <span class="label">Estado:</span>
        <?php if ($activa): ?>
          <span class="badge active">Activa</span>
        <?php else: ?>
          <span class="badge inactive">Inactiva</span>
        <?php endif; ?>
      </div>
      <?php if ($activa): ?>
        <div class="row">
          <span class="label">Tiempo restante:</span>
          <span class="value" id="tleft">—</span>
        </div>
        <div class="progress-bar">
          <div class="progress-bar-inner" id="progressInner"></div>
        </div>
      <?php else: ?>
        <div class="row">
          <span class="value">Tu estadía no está activa.</span>
        </div>
      <?php endif; ?>
    </div>

    <?php if ($activa): ?>
    <div class="video-container">
      <video id="video" controls autoplay muted playsinline></video>
    </div>
    <?php endif; ?>

    <?php if (!$activa): ?>
      <div class="message">
        Actualmente no tienes acceso al streaming.<br/>
        Si tu estadía aún no comenzó, vuelve cuando llegue el horario de inicio.<br/>
        Si tu estadía ya finalizó, esperamos volver a verte pronto.
      </div>
    <?php else: ?>
      <div class="message">
        <p>Si necesitas ayuda o tenés alguna consulta, comunícate al 11-3378-6503 (linea o whatsapp).</p>
        <p>José Ingenieros 880, La Lucila, Buenos Aires, Argentina.</p>
      </div>
    <?php endif; ?>
  </div>

  <script>
    // Variables generadas en PHP para JS
    const ACTIVE        = <?= $activa ? 'true' : 'false' ?>;
    const REMAINING     = <?= (int)$remainingSeconds ?>; // segundos restantes
    const TOTAL_SECONDS = <?= (int)$totalSeconds ?>;     // duración total en segundos
    const STREAM_SRC    = '/hls/cam1.m3u8';               // Ruta al manifiesto HLS

    const videoEl      = document.getElementById('video');
    const tleftEl      = document.getElementById('tleft');
    const progressInner= document.getElementById('progressInner');

    let hlsInstance = null;
    let remaining   = REMAINING;
    let tickId      = null;

    // Función para formatear segundos en días, horas, minutos y segundos (dd:HH:MM:SS)
    function formatDHMS(totalSeconds) {
      const s  = Math.max(0, totalSeconds | 0);
      const d  = Math.floor(s / 86400);
      const h  = Math.floor((s % 86400) / 3600);
      const m  = Math.floor((s % 3600) / 60);
      const sec= s % 60;
      const pad= n => String(n).padStart(2, '0');
      const dayStr = d > 0 ? d + (d === 1 ? ' día, ' : ' días, ') : '';
      return dayStr + `${pad(h)}:${pad(m)}:${pad(sec)}`;
    }

    function updateProgress() {
      if (progressInner) {
        const elapsed = TOTAL_SECONDS - remaining;
        let percent = Math.min(100, Math.max(0, (elapsed / TOTAL_SECONDS) * 100));
        progressInner.style.width = percent.toFixed(2) + '%';
      }
    }

    function stopPlayback(reason = '') {
      try {
        if (hlsInstance) {
          hlsInstance.destroy();
          hlsInstance = null;
        }
        if (videoEl) {
          videoEl.pause();
          videoEl.src = '';
        }
        if (tickId) {
          clearInterval(tickId);
          tickId = null;
        }
        // Actualiza la interfaz cuando termina
        if (tleftEl) {
          tleftEl.textContent = '00:00:00';
        }
        updateProgress();
        // Oculta el video y muestra el mensaje de finalización
        const msgDiv = document.querySelector('.message');
        if (msgDiv) {
          msgDiv.innerHTML = 'Tu tiempo ha finalizado. ¡Gracias por utilizar IziPark!';
        }
      } catch (e) {
        // Silenciar errores en finalización
      }
    }

    function startPlayback() {
      if (!ACTIVE) return;

      if (typeof Hls !== 'undefined' && Hls.isSupported()) {
        hlsInstance = new Hls({ lowLatencyMode: true });
        hlsInstance.loadSource(STREAM_SRC);
        hlsInstance.attachMedia(videoEl);
        hlsInstance.on(Hls.Events.ERROR, function (event, data) {
          if (data.fatal) {
            switch (data.type) {
              case Hls.ErrorTypes.NETWORK_ERROR:
                hlsInstance.startLoad();
                break;
              case Hls.ErrorTypes.MEDIA_ERROR:
                hlsInstance.recoverMediaError();
                break;
              default:
                stopPlayback('error de reproducción');
                break;
            }
          }
        });
      } else if (videoEl && videoEl.canPlayType('application/vnd.apple.mpegurl')) {
        videoEl.src = STREAM_SRC;
      } else {
        // Sin soporte HLS
        const msgDiv = document.querySelector('.message');
        if (msgDiv) {
          msgDiv.innerHTML = 'Tu navegador no soporta el formato de streaming. Intenta con otro dispositivo.';
        }
        return;
      }

      // Inicializa el contador y barra
      if (tleftEl) {
        tleftEl.textContent = formatDHMS(remaining);
      }
      updateProgress();
      tickId = setInterval(() => {
        remaining -= 1;
        if (remaining <= 0) {
          stopPlayback('tiempo agotado');
        } else {
          if (tleftEl) {
            tleftEl.textContent = formatDHMS(remaining);
          }
          updateProgress();
        }
      }, 1000);
    }

    if (ACTIVE) {
      startPlayback();
    }
  </script>
</body>
</html>