<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Invitation à rejoindre un thème</title>
    <style>
        .container {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            padding: 1rem;
            max-width: 600px;
            margin: auto;
            background-color: #f8f9fa;
            border-radius: 1rem;
        }

        .title {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            text-align: center;
            width: 100%;
            border-bottom: 1px solid #dee2e6;
        }

        .btn-accept {
            background-color: #28a745;
            color: #fff;
            padding: 1rem 2rem;
            text-decoration: none;
            border-radius: 1rem;
            display: inline-block;
            font-size: 1rem;
        }

        .btn-decline {
            background-color: #dc3545;
            color: #fff;
            padding: 1rem 2rem;
            text-decoration: none;
            border-radius: 1rem;
            display: inline-block;
            font-size: 1rem;
        }

        .foot {
            font-size: 0.75rem;
            text-align: center;
            max-width: 600px;
            margin: 1rem auto 0;
        }

        .buttons-table {
            width: 100%;
            margin: 4rem 0;
        }

        .buttons-table td {
            text-align: center;
            vertical-align: middle;
            width: 50%;
        }
    </style>
</head>
<body>
<div class="container">
    <h1 class="title">
        Invitation à rejoindre le thème {{ $theme->title }}
    </h1>
    <p>
        Bonjour {{ $invitee->first_name ?: $invitee->username }},
    </p>
    <p>
        <strong>{{ $inviter->first_name ? $inviter->first_name . ' ' . $inviter->last_name : $inviter->username }}</strong>
        vous invite à rejoindre le thème <strong>{{ $theme->title }}</strong>.
    </p>

    <!-- Tableau pour les boutons (compatible email) -->
    <table class="buttons-table" cellpadding="0" cellspacing="0">
        <tr>
            <td>
                <a href="{{ $acceptLink }}" class="btn-accept">
                    Accepter l'invitation
                </a>
            </td>
            <td>
                <a href="{{ $declineLink }}" class="btn-decline">
                    Refuser l'invitation
                </a>
            </td>
        </tr>
    </table>

    <p>
        Merci,<br>
        {{ config('app.name') }}
    </p>
</div>

<!-- Footer avec div simples (pas de flexbox) -->
<div class="foot">
    <div>
        Si les boutons ne fonctionnent pas, vous pouvez également cliquer sur ces liens :
    </div>
    <div style="margin-top: 0.5rem; text-align: start;">
        Accepter : <a href="{{ $acceptLink }}">{{ $acceptLink }}</a>
    </div>
    <div style="margin-top: 0.5rem; text-align: start;">
        Refuser : <a href="{{ $declineLink }}">{{ $declineLink }}</a>
    </div>
</div>
</body>
</html>
