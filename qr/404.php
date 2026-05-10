<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>404 Not Found - MB Logistics</title>
  <style>
    body, html {
      height: 100%;
      margin: 0;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(135deg, #1e3c72, #2a5298);
      color: #ffffff;
      display: flex;
      justify-content: center;
      align-items: center;
      text-align: center;
      padding: 20px;
      flex-direction: column;
    }
    .container {
      max-width: 480px;
      background: rgba(255, 255, 255, 0.1);
      border-radius: 12px;
      padding: 30px 20px 40px 20px;
      box-shadow: 0 8px 24px rgba(0,0,0,0.2);
      backdrop-filter: blur(10px);
      width: 100%;
    }
    h1 {
      font-size: 96px;
      font-weight: 900;
      letter-spacing: 8px;
      margin-bottom: 10px;
      color: #ff6f61;
    }
    h2 {
      font-size: 28px;
      margin-bottom: 16px;
      font-weight: 700;
    }
    p {
      font-size: 18px;
      margin-bottom: 20px;
      line-height: 1.5;
      color: #e0e0e0;
    }
    a.button {
      display: inline-block;
      background-color: #ff6f61;
      color: white;
      padding: 14px 28px;
      border-radius: 30px;
      font-weight: 600;
      text-decoration: none;
      transition: background-color 0.3s ease;
      box-shadow: 0 4px 15px rgba(255, 111, 97, 0.4);
      margin-top: 20px;
    }
    a.button:hover {
      background-color: #e85b50;
      box-shadow: 0 6px 20px rgba(232, 91, 80, 0.6);
    }
    #qr-reader {
      width: 100%;
      margin: 0 auto;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 4px 15px rgba(255, 111, 97, 0.4);
    }
    #qr-result {
      margin-top: 20px;
      font-size: 16px;
      color: #ffdede;
      min-height: 24px;
      word-break: break-all;
    }
    @media (max-width: 480px) {
      h1 {
        font-size: 72px;
        letter-spacing: 6px;
      }
      h2 {
        font-size: 24px;
      }
      p {
        font-size: 16px;
      }
    }
  </style>
  <!-- Include html5-qrcode library -->
  <script src="https://cdn.jsdelivr.net/npm/html5-qrcode/minified/html5-qrcode.min.js"></script>

</head>
<body>
  <div class="container" role="main" aria-labelledby="error-title">
    <h1 id="error-title">404</h1>
    <h2>Don't come like this!</h2>
    <p>Scan your code from the voucher. Thanks you.</p>

    <div id="qr-reader" aria-label="QR code scanner"></div>
    <div id="qr-result" aria-live="polite" aria-atomic="true"></div>
    
    <script>
    function onScanSuccess(decodedText, decodedResult) {
      // Handle on success condition with the decoded text or result.
      const resultContainer = document.getElementById('qr-result');
      resultContainer.textContent = `Scanned code: ${decodedText}`;

      // Optionally, redirect if the scanned code is a URL
      if (decodedText.startsWith('http://') || decodedText.startsWith('https://')) {
        resultContainer.textContent += ' - Redirecting...';
        setTimeout(() => {
          window.location.href = decodedText;
        }, 2000);
      }

      // Stop scanning after successful scan
      html5QrcodeScanner.clear().catch(error => {
        console.error('Failed to clear QR scanner.', error);
      });
    }

    function onScanFailure(error) {
      // You can ignore scan failures or log them for debugging.
       console.warn(`QR scan error: ${error}`);
    }

    let html5QrcodeScanner = new Html5Qrcode("qr-reader");

    // Start scanning with camera, prefer rear camera if available
    Html5Qrcode.getCameras().then(cameras => {
      if (cameras && cameras.length) {
        let cameraId = cameras[0].id;
        // Try to find a rear camera if possible
        for (let cam of cameras) {
          if (cam.label.toLowerCase().includes('back') || cam.label.toLowerCase().includes('rear')) {
            cameraId = cam.id;
            break;
          }
        }
        html5QrcodeScanner.start(
          cameraId,
          {
            fps: 10,    // frames per second
            qrbox: 250  // scanning box size
          },
          onScanSuccess,
          onScanFailure
        ).catch(err => {
          const resultContainer = document.getElementById('qr-result');
          resultContainer.textContent = 'Unable to start scanning. Please allow camera access or use a supported device.';
          console.error(err);
        });
      } else {
        document.getElementById('qr-result').textContent = 'No camera found on this device.';
      }
    }).catch(err => {
      document.getElementById('qr-result').textContent = 'Error accessing cameras: ' + err;
    });
  </script>

    <a href="https://mblogistics.express" class="button" aria-label="Go back to mblogistics homepage">Go Back to mblogistics.express</a>
  </div>

  
</body>
</html>
