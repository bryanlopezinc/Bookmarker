<!DOCTYPE NETSCAPE-Bookmark-file-1>
<HTML>
<META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=UTF-8">
<Title>Bookmarks</Title>
<H1>Bookmarks</H1>
<DT>
	<H3 FOLDED>Favourites</H3>
	<DL><p>
			@foreach($bookmarks as $bookmark)
			<DT><A HREF="{{$bookmark['url'] ?? 'http://www.apple.com/'}}">Apple</A>
			@endforeach
	</DL><p>
<DT>
	<H3 FOLDED>Bookmarks Menu</H3>
	<DL><p>
	</DL><p>
<!-- <DT><A HREF="http://www.bing.com/?pc=APPM">Bing</A> -->
<DT>
	<H3 FOLDED id="com.apple.ReadingList">Reading List</H3>
	<DL><p>
			<!-- <DT><A HREF="https://en.m.wikipedia.org/wiki/Phenylketonuria">Phenylketonuria - Wikipedia, the free encyclopedia</A> -->
	</DL><p>

<!-- <DT><A HREF="http://www.google.com/">Google</A> -->
</HTML>