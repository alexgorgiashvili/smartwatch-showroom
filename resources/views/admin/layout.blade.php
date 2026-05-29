<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="webpush-public-key" content="{{ config('services.webpush.public_key') }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="MyTechnic Admin">
    <meta name="theme-color" content="#ffffff">
    <title>@yield('title', 'Admin')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700;900&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('assets/vendors/core/core.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/fonts/feather-font/css/iconfont.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/flag-icon-css/css/flag-icon.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/demo1/style.css') }}">
    <link rel="manifest" href="/site.webmanifest">
    <link rel="apple-touch-icon" href="/assets/images/favicon.png">
    <link rel="shortcut icon" href="/assets/images/favicon.png" />

    <link rel="stylesheet" href="{{ asset('assets/vendors/sweetalert2/sweetalert2.min.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css">
    <link rel="stylesheet" href="{{ asset('assets/vendors/datatables.net-bs4/dataTables.bootstrap4.css') }}">
    @stack('styles')
</head>
<body>
    <div id="pjax-progress" class="pjax-progress"></div>
    <div class="main-wrapper">
        @if(auth()->check() && ! request()->routeIs('admin.login'))
            @include('admin.partials.sidebar')
            <div class="page-wrapper">
                @include('admin.partials.navbar')
                <div class="page-content">
                    <div id="page-content">
                        @include('admin.partials.alerts')
                        @yield('content')
                    </div>
                </div>
                @include('admin.partials.footer')
            </div>
        @else
            <div class="page-wrapper full-page">
                <div class="page-content d-flex align-items-center justify-content-center">
                    <div class="w-100">
                        @include('admin.partials.alerts')
                        @yield('content')
                    </div>
                </div>
            </div>
        @endif
    </div>

    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080;">
        <div id="async-toast" class="toast align-items-center bg-dark text-white border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true" style="min-width: 280px;">
            <div class="d-flex">
                <div class="toast-body"></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <script src="{{ asset('assets/vendors/core/core.js') }}"></script>
    <script src="{{ asset('assets/vendors/feather-icons/feather.min.js') }}"></script>
    <script src="{{ asset('assets/js/template.js') }}"></script>
    <script src="{{ asset('assets/vendors/sweetalert2/sweetalert2.min.js') }}"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
    <script src="{{ asset('assets/vendors/datatables.net/jquery.dataTables.js') }}"></script>
    <script src="{{ asset('assets/vendors/datatables.net-bs4/dataTables.bootstrap4.js') }}"></script>
    @vite(['resources/js/admin.js', 'resources/css/admin.css'])
    @stack('scripts')

    <!-- Global Lightbox Modal -->
    <div id="global-lightbox" class="lightbox-modal">
        <div class="lightbox-header">
            <button class="lightbox-close" id="lightbox-close" aria-label="Close">
                <i data-feather="x"></i>
            </button>
        </div>
        <div class="lightbox-content">
            <button class="lightbox-nav lightbox-prev" id="lightbox-prev" aria-label="Previous">
                <i data-feather="chevron-left"></i>
            </button>
            <img src="" alt="Gallery Image" class="lightbox-img" id="lightbox-main-img">
            <button class="lightbox-nav lightbox-next" id="lightbox-next" aria-label="Next">
                <i data-feather="chevron-right"></i>
            </button>
        </div>
        <div class="lightbox-thumbnails" id="lightbox-thumbnails"></div>
    </div>
</body>
</html>
