<!DOCTYPE html>
<html lang="en" dir="ltr" class="light" data-bs-theme="light">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{{ $title }}</title>

  <meta property="og:title" content="{{ $title }}" />
  <meta property="og:image" content="{{ $imageUrl }}" />

  <!-- Favicons -->
  <link rel="icon" type="image/x-icon" href="{{ $imageUrl }}">
  <link rel="shortcut icon" href="{{ asset('asset/img/favicon.png')}}" type="image/x-icon">

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    html,
    body {
      height: 100%;
      margin: 0;
      padding: 0;
    }

    body.download-section {
      position: relative;
      min-height: 100vh;
      margin: 0;
      padding: 0;
    }

    .background-blur {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: url({{ $imageUrl }}) center/cover no-repeat;
      filter: blur(20px) brightness(0.4);
      transform: scale(1.1);
      z-index: -1;
    }

    .content-wrapper {
      position: relative;
      z-index: 1;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .app-image {
      border-radius: 20px;
      box-shadow: 0px 8px 20px rgba(0, 0, 0, 0.4);
      width: 140px;
      height: 140px;
      object-fit: cover;
    }

    .logo-lg img {
      height: 60px;
    }

    h1,
    p {
      color: #fff;
    }

    .download-buttons img {
      background: #000;
      border-radius: 12px;
      padding: 8px 12px;
      height: 60px;
      width: auto;
      transition: transform 0.2s ease-in-out;
    }

    .download-buttons img:hover {
      transform: scale(1.05);
    }

    .custom-card {
      position: relative;
      margin: 0;
      border-radius: 32px;
      background-color: #0000009e;
      -webkit-box-shadow: 8px 16px 32px rgb(35 58 79 / 35%), 20px 30px 80px rgb(73 73 93 / 40%);
      -moz-box-shadow: 8px 16px 32px rgba(35, 58, 79, 0.35), 20px 30px 80px rgba(73, 73, 93, 0.4);
      box-shadow: 8px 16px 32px rgb(24 24 24), 20px 30px 80px rgb(73 73 93 / 40%);
      padding: 0;
      z-index: 100;
      width: 250px;
      min-height: 480px;
      overflow: hidden;
      outline: 3px solid #ffffff1a;
      float: left;
      color: #fff;
      padding: 30px 20px 20px;
      align-items: center;
    }


    .text-ellipses {
      display: -webkit-box;
      -webkit-line-clamp: 4;
      /* limit to 4 lines */
      -webkit-box-orient: vertical;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    @media (max-width: 992px) {
      h1.display-5 {
        font-size: 2rem;
      }

      p.lead {
        font-size: 1rem;
      }

      .download-buttons img {
        height: 50px;
      }
    }

    @media (max-width: 768px) {
      .content-wrapper {
        align-items: flex-start;
        padding-top: 2rem;
      }
    }

    @media (max-width: 576px) {

      .col-lg-5,
      .col-lg-7 {
        text-align: center;
      }

      .logo-lg img {
        height: 50px;
      }

      .download-buttons {
        justify-content: center;
      }
    }
  </style>
</head>

<body class="download-section">
  <div class="background-blur"></div>
  <div class="container content-wrapper">
    <div class="row align-items-center w-100 py-5">
      <div class="col-lg-5 text-center mb-4 mb-lg-0 d-flex justify-content-center">
        <div class="custom-card">
          @if($contentType === 'image' || $contentType === 'video')
          <div class="position-relative d-inline-block">
            <img src="{{ $imageUrl }}" class="app-image mb-3" alt="Post Preview" loading="lazy">
          </div>
          @elseif($contentType === 'audio')
          <div class="mb-3">
            <img src="{{ $imageUrl }}" class="app-image mb-3" alt="Post Preview" loading="lazy">
            {{ $contentType }}
          </div>
          @else
          <img src="{{ $imageUrl }}" class="app-image mb-3" alt="Default Preview" loading="lazy">
          @endif
          <h6 style="color: #ffffff80;">{{ $setting->app_name }}</h6>
          <h4 class="text-ellipses">{{ $title }}</h4>
        </div>
      </div>
      <div class="col-lg-7 text-center text-lg-start pb-4">
        <div class="logo-lg mb-4">
          <img src="{{ asset('asset/img/favicon.png')}}" alt="logo">
        </div>
        <h1 class="display-5 fw-bold mb-3"> Download <br> the app now. </h1>
        <div class="d-flex gap-3 download-buttons flex-wrap justify-content-lg-start justify-content-center">
          <a href="{{ $setting->play_store_download_link }}" target="_blank">
            <img src="{{ asset('asset/img/playstore.png') }}" alt="Download on Play Store">
          </a>
          <a href="{{ $setting->app_store_download_link }}" target="_blank">
            <img src="{{ asset('asset/img/appstore.png') }}" alt="Download on App Store">
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

  <!-- App Scheme Redirect -->
  <script>
    document.addEventListener("DOMContentLoaded", function() {
      var appUrl = "{{ $setting->uri_scheme }}://s/{{ $shareLinkPage }}";
      window.location.href = appUrl;
    });
  </script>
</body>

</html>