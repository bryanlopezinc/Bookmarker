<!DOCTYPE html>
<html>
	<!--So long and thanks for all the fish-->
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<title>Pocket Export</title>
	</head>
	<body>
		<h1>Unread</h1>
		<ul>
			 @foreach($bookmarks as $bookmark)
            <li><a href="{{$bookmark['url'] ?? 'https://www.sitepoint.com/build-restful-apis-best-practices/'}}"
					time_added="1622473612"
					tags="{{$bookmark['tags']}}">https://www.sitepoint.com/build-restful-apis-best-practices/</a></li>
        	@endforeach
		</ul>

		<h1>Read Archive</h1>
		<ul>
			<!-- <li><a href="https://www.zaproxy.org/getting-started/"
					time_added="1622342916"
					tags="">https://www.zaproxy.org/getting-started</a></li> -->
		</ul>
	</body>
</html>