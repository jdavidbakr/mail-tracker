<head>
    This is the head
</head>
<body>
<h1>
    This is a test for dont-track attribute!
</h1>
<p>
    This link should be tracked:
    <a href="http://www.tracked-link.com">Click Here to be Tracked</a>
</p>
<p>
    This link should NOT be tracked:
    <a data-dont-track href="http://www.untracked-link.com">Click Here Without Tracking</a>
</p>
<p>
    Another tracked link:
    <a class="test" href="http://www.google.com?q=foo&amp;x=bar">
        Click here for Google
    </a>
</p>
<p>
    Another untracked link with attributes:
    <a class="test" data-dont-track href="http://www.privacy-link.com">
        Privacy Link
    </a>
</p>
</body>
