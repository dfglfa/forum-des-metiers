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
    h1 { font-size: 1.25rem; color: #1a1a2e; margin: 0 0 1.25rem; }
    p { color: #374151; line-height: 1.7; margin: 0 0 1rem; white-space: pre-wrap; }
    .btn { display: inline-block; margin-top: 1rem; padding: 0.75rem 1.5rem; background: #4f46e5; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600; }
    .note { margin-top: 1.5rem; font-size: 0.8rem; color: #9ca3af; }
    .divider { border: none; border-top: 1px solid #f3f4f6; margin: 1.5rem 0; }
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
    @if ($language === 'fr')
      <h1>Invitation au {{ $eventTitle }}{{ $eventDate ? ' du ' . $eventDate : '' }}</h1>
      <p>{{ $body }}</p>
      <hr class="divider">
      <p>Cliquez sur le bouton ci-dessous pour définir votre mot de passe et activer votre compte :</p>
      <a href="{{ $link }}" class="btn">Définir mon mot de passe</a>
      <p class="note">Ce lien est valable 7 jours. Si vous ne vous attendiez pas à cet e-mail, vous pouvez l'ignorer.</p>
    @else
      <h1>Einladung zum {{ $eventTitle }}{{ $eventDate ? ' am ' . $eventDate : '' }}</h1>
      <p>{{ $body }}</p>
      <hr class="divider">
      <p>Klicke auf die Schaltfläche unten, um dein Passwort festzulegen und dein Konto zu aktivieren:</p>
      <a href="{{ $link }}" class="btn">Passwort festlegen</a>
      <p class="note">Dieser Link ist 7 Tage gültig. Falls du diese E-Mail nicht erwartet hast, kannst du sie ignorieren.</p>
    @endif
  </div>
</body>
</html>
