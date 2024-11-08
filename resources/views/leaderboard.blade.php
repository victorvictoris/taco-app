<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard - TacoApp</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            background-color: #ffe450;
        }
        .header-logo {
            display: block;
            margin: 0 auto;
            width: 100px;
        }
        .table {
            background-color: #fff;
        }
    </style>
</head>
<body>
<div class="container mt-5">
    <img src="./totallynotheytaco.png" alt="TacoApp Logo" class="header-logo mb-3"> <!-- Ubacite putanju do vašeg logotipa ovde -->
    <h1 class="text-center mb-4">🏆 Leaderboard 🏆</h1>
    <table class="table table-striped table-bordered">
        <thead class="table-dark">
        <tr>
            <th>#</th>
            <th>Profilna Slika</th>
            <th>Korisnik</th>
            <th>Primljeni Takosi 🌮</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($leaderboard as $index => $user)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>
                    @if ($user->profile_picture)
                        <img src="{{ $user->profile_picture }}" alt="Profile Picture" width="50" height="50" class="rounded-circle">
                    @else
                        <span>Nema slike</span>
                    @endif
                </td>
                <td>{{ $user->name }}</td>
                <td>{{ $user->received_tacos_sum_number_of_given_tacos }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
</body>
</html>
