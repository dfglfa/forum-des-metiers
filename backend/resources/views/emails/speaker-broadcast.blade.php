<!DOCTYPE html>
<html lang="{{ $language }}">
<head>
  <meta charset="UTF-8">
  <style>
    body { font-family: sans-serif; background: #f4f4f5; margin: 0; padding: 2rem 1rem; }
    .card { background: #fff; border-radius: 8px; max-width: 520px; margin: 0 auto; padding: 2rem 2.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
    .logo { display: block; max-width: 180px; max-height: 80px; margin: 0 0 1.25rem; }
    p { color: #374151; line-height: 1.7; margin: 0 0 1rem; white-space: pre-wrap; }
  </style>
</head>
<body>
  <div class="card">
    @if ($logoUrl)
      <img src="{{ $logoUrl }}" alt="{{ $eventTitle }}" class="logo">
    @endif
    <p>{{ $body }}</p>
  </div>
</body>
</html>
