<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>Instapaper: Export</title>
</head>

<body>
    <h1>Unread</h1>
    <ol>
        @foreach($bookmarks as $bookmark)
        <li><a href="{{$bookmark['url'] ?? 'https://symfony.com/'}}">Symfony, High Performance PHP Framework for Web Development</a>
            @endforeach
    </ol>
</body>

</html>