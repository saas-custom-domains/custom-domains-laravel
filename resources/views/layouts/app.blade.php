<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
    <style>
        body { font: 16px/1.5 system-ui, sans-serif; color: #18181b; margin: 0; }
        main { max-width: 42rem; margin: 0 auto; padding: 3rem 1.5rem; }
        h1 { font-size: 1.6rem; margin: 0; }
        section { border: 1px solid #e4e4e7; border-radius: .75rem; padding: 1.25rem; margin-top: 2rem; }
        form { margin: 0; }
        input { font: inherit; padding: .35rem .5rem; border: 1px solid #d4d4d8; border-radius: .375rem; }
        button { font: inherit; cursor: pointer; }
        .muted { color: #52525b; }
        .row { display: flex; gap: .75rem; align-items: center; flex-wrap: wrap; }
        .domains { list-style: none; padding: 0; }
        .domains li { display: flex; gap: .75rem; align-items: center; font-size: .9rem; margin: .5rem 0; }
        .badge { border-radius: .25rem; padding: .1rem .5rem; background: #fef3c7; }
        .badge.ok { background: #dcfce7; }
        .link { background: none; border: 0; padding: 0; text-decoration: underline; }
        .danger { color: #b91c1c; }
        .notice { background: #dcfce7; padding: .75rem 1rem; border-radius: .5rem; }
        .error { color: #b91c1c; }
    </style>
</head>
<body>
<main>
    @yield('content')
</main>
</body>
</html>
