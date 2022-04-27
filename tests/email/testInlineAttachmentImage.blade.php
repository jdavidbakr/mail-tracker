<head>
    This is the head
</head>
<body>
<h1>
    This is a test!
</h1>
<p>
    To embed an inline image, use the embed method on the $message variable within your email template. Laravel automatically makes the $message variable available to all of your email templates, so you don't need to worry about passing it in manually:
</p>
@php
    file_put_contents('test.jpg', file_get_contents('https://upload.wikimedia.org/wikipedia/en/9/95/Test_image.jpg'))
@endphp
<img src="{{ $message->embed('test.jpg') }}">
</body>
