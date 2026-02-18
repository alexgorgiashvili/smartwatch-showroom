<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-900">
    <div class="min-h-screen">
        <!-- Admin Header -->
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6">
                <div class="flex items-center gap-3">
                    <span class="font-bold text-lg">Admin Panel</span>
                </div>
                @auth
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button type="submit" class="text-sm font-semibold text-gray-600 hover:text-blue-600">Logout</button>
                    </form>
                @endauth
            </div>
        </header>

        <!-- Admin Content -->
        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
            @if (session('status'))
                <div class="mb-4 rounded-lg bg-green-50 border border-green-200 p-4 text-sm text-green-700">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 rounded-lg bg-red-50 border border-red-200 p-4">
                    <ul class="list-inside text-sm text-red-700">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</body>
</html>
