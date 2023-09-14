<?php 
$accessToken = $_['accessToken'];
$actionUrl = $_['actionUrl'];
$wopiUrl = $_['wopiUrl'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0 minimum-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>OxOffice online</title>
  <style>
    html, body, iframe {
      margin: 0;
      padding: 0;
      border: none;
    }

    iframe {
      width: 100%;
      height: 100%;
      position: absolute;
    }
  </style>
</head>
<body>
  <form action="<?php p($actionUrl) ?>" id="preview-form" enctype="multipart/form-data" target="preview-frame" method="post">
    <input type="hidden" name="access_token" value="<?php p($accessToken) ?>">
  </form>
  <iframe name="preview-frame" scrolling="no" allowfullscreen></iframe>
  <script nonce="<?php p(\OC::$server->getContentSecurityPolicyNonceManager()->getNonce()) ?>">
    const previewForm = document.getElementById('preview-form')
    const previewFrame = document.getElementById('preview-frame')
    const inIFrame = (window.location !== window.parent.location)

    window.addEventListener('message', (e) => {
      if (e.origin === '<?php p($wopiUrl) ?>') {
        const data = JSON.parse(e.data);
        if (data.MessageId === 'UI_Close') {
          window.close()
        }
      }
    }, false)
    previewForm.submit()
  </script>
</body>
</html>