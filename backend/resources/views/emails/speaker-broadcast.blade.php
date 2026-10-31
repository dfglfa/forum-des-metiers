<!DOCTYPE html>
<html lang="{{ $language }}">
<head>
  <meta charset="UTF-8">
  <style>
    body { font-family: sans-serif; background: #f4f4f5; margin: 0; padding: 2rem 1rem; }
    .card { background: #fff; border-radius: 8px; max-width: 520px; margin: 0 auto; padding: 2rem 2.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
    .header { margin: 0 0 1.25rem; border-collapse: collapse; }
    .header td { padding: 0; vertical-align: middle; }
    .logo { display: block; max-width: 90px; max-height: 40px; }
    .event-title { padding-left: 0.75rem !important; font-size: 1rem; font-weight: 600; color: #1a1a2e; }
    p { color: #374151; line-height: 1.7; margin: 0 0 1rem; white-space: pre-wrap; }
  </style>
</head>
<body>
  <div class="card">
    <table class="header" role="presentation">
      <tr>
        @if ($logoUrl)
          <td><img src="{{ $logoUrl }}" alt="{{ $eventTitle }}" class="logo"></td>
        @endif
        <td class="event-title">{{ $eventTitle }}</td>
      </tr>
    </table>
    <p>{{ $body }}</p>
  </div>
</body>
</html>
