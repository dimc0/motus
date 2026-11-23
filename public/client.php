<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Motus</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Alfa+Slab+One&family=Karla:wght@400;600;700&display=swap">
    <link rel="stylesheet" href="/assets/css/motus.css">
    <script src="/assets/js/jquery-3.7.1.min.js"></script>
</head>
<body>
<main class="container">
    <h1 class="logo" aria-label="Motus"><span>M</span><span>O</span><span>T</span><span>U</span><span>S</span></h1>
    <div class="tagline">
        <p>Le jeu de lettres où il faut trouver le mot de 6 lettres avant l'autre. Chacun son tour, 8 secondes pour proposer, 8 essais en tout.</p>
        <p class="box-age">Pour 2 joueurs, dès 10 ans</p>
    </div>

    <!-- 3.2 Formulaire d'authentification -->
    <form id="login-form">
        <h2>Connexion</h2>
        <label>Email <input type="email" name="email" required></label>
        <label>Mot de passe <input type="password" name="password" required></label>
        <button type="submit">Se connecter</button>
        <p id="login-message"></p>
    </form>

    <!-- 3.4 Formulaire de création de compte -->
    <form id="signup-form">
        <h2>Créer un compte</h2>
        <div class="fields">
            <label>Prénom <input type="text" name="first_name" required></label>
            <label>Nom <input type="text" name="last_name" required></label>
        </div>
        <div class="fields">
            <label>Pseudo <input type="text" name="pseudo" required></label>
            <label>Date de naissance <input type="date" name="birth_date" required></label>
        </div>
        <div class="fields">
            <label>Genre
                <select name="gender" required>
                    <option value="m">Homme</option>
                    <option value="f">Femme</option>
                    <option value="o">Autre</option>
                </select>
            </label>
            <label>Avatar <input type="text" name="avatar" placeholder="mon-avatar.jpg"></label>
        </div>
        <label>Email <input type="email" name="email" required></label>
        <label>Mot de passe <input type="password" name="password" required></label>
        <button type="submit">Créer mon compte</button>
        <p id="signup-message"></p>
    </form>

    <!-- Affiché uniquement une fois authentifié -->
    <section id="logged-in" style="display: none;">
        <!-- 3.3 Formulaire de désauthentification -->
        <div class="topbar">
            <p>Vous êtes connecté.</p>
            <form id="logout-form">
                <button type="submit" class="ghost">Se déconnecter</button>
            </form>
        </div>
        <p id="logout-message"></p>

        <!-- 4. Motus : liste des joueurs connectés et disponibles -->
        <section id="lobby">
            <h2>Joueurs disponibles</h2>
            <p><small>Cliquez sur un joueur pour lancer une partie : vous jouerez en premier.</small></p>
            <div id="players-list"></div>
            <p id="lobby-message"></p>
        </section>

        <!-- 4. Motus : partie en cours -->
        <section id="game" style="display: none;">
            <h2 id="game-title"></h2>
            <p id="game-status"></p>
            <table id="grid"></table>

            <form id="guess-form" style="display: none;">
                <div class="inline-field">
                    <input type="text" name="word" maxlength="6" autocomplete="off" placeholder="Mot de 6 lettres" required>
                    <button type="submit">Proposer</button>
                </div>
            </form>
            <p id="guess-message"></p>

            <ul class="legend">
                <li><i class="R"></i>Lettre bien placée</li>
                <li><i class="J"></i>Lettre présente mais mal placée</li>
                <li><i class="B"></i>Lettre absente du mot</li>
            </ul>

            <button type="button" id="back-to-lobby" class="ghost" style="display: none;">Retour à la liste des joueurs</button>
        </section>

        <div id="account">
        <!-- 3.5 Formulaire de modification du compte -->
        <form id="update-form">
            <h2>Modifier mon profil</h2>
            <p><small>Seuls les champs remplis sont modifiés.</small></p>
            <div class="fields">
                <label>Pseudo <input type="text" name="pseudo"></label>
                <label>Genre
                    <select name="gender">
                        <option value="">Inchangé</option>
                        <option value="m">Homme</option>
                        <option value="f">Femme</option>
                        <option value="o">Autre</option>
                    </select>
                </label>
                <label>Avatar <input type="text" name="avatar"></label>
            </div>
            <button type="submit">Enregistrer</button>
            <p id="update-message"></p>
        </form>

        <!-- 3.6 Formulaire de suppression du compte -->
        <form id="delete-form">
            <button type="submit" class="danger">Supprimer mon compte</button>
        </form>
        <p id="delete-message"></p>
        </div>
    </section>
</main>
<script>
    const API_URL = '/api';

    // Header à joindre aux routes protégées
    function authHeader() {
        return {'Authorization': 'Bearer ' + localStorage.getItem('accessToken')};
    }

    // L'id de l'utilisateur connecté est dans le payload du JWT (2e partie, encodée en base64url)
    function currentUserId() {
        const payload = localStorage.getItem('accessToken').split('.')[1];
        return JSON.parse(atob(payload.replace(/-/g, '+').replace(/_/g, '/'))).user_id;
    }

    function showMessage(selector, text, isError) {
        $(selector).text(text).removeClass('success error').addClass(isError ? 'error' : 'success');
    }

    // Affichage selon l'état d'authentification
    function showLoggedIn() {
        $('#login-form, #signup-form, .tagline').hide();
        $('#logged-in').show();
        $('#game').hide();
        $('#lobby, #account').show();
        startPolling();
    }

    function showLoggedOut() {
        stopPolling();
        $('#logged-in').hide();
        $('#login-form, #signup-form, .tagline').show();
    }

    function endSession(message, isError) {
        localStorage.removeItem('accessToken');
        showLoggedOut();
        showMessage('#login-message', message, isError);
    }

    function authenticate(email, password) {
        $.ajax({
            url: API_URL + '/login',
            method: 'POST',
            data: {email: email, password: password},
            success: function (response) {
                localStorage.setItem('accessToken', response.data.accessToken);
                $('#login-form')[0].reset();
                $('#login-message').text('');
                showLoggedIn();
            },
            error: function (xhr) {
                const message = xhr.status === 401 ? 'Email ou mot de passe incorrect' : 'Erreur serveur, réessayez';
                showMessage('#login-message', message, true);
            }
        });
    }

    function deauthenticate() {
        authAjax({
            url: API_URL + '/logout',
            method: 'POST',
            success: function () {
                endSession('Vous êtes déconnecté', false);
            },
            error: function () {
                showMessage('#logout-message', 'Erreur lors de la déconnexion', true);
            }
        });
    }

    // 3.7 Le refresh token (cookie httponly) est envoyé automatiquement par le navigateur
    function reauthenticate(callback) {
        $.ajax({
            url: API_URL + '/token/refresh',
            method: 'POST',
            success: function (response) {
                localStorage.setItem('accessToken', response.data.accessToken);
                callback();
            },
            error: function () {
                // Refresh token expiré ou révoqué : il faut se reconnecter
                endSession('Session expirée, reconnectez-vous', true);
            }
        });
    }

    // 3.7 Requête vers une route protégée. Si l'access token a expiré (401),
    // on récupère un nouveau token puis on relance la requête une seule fois.
    function authAjax(options) {
        const send = function (onUnauthorized) {
            $.ajax($.extend({}, options, {
                headers: authHeader(),
                error: function (xhr) {
                    if (xhr.status === 401) return onUnauthorized();
                    options.error(xhr);
                }
            }));
        };

        send(function () {
            reauthenticate(function () {
                send(function () {
                    endSession('Session expirée, reconnectez-vous', true);
                });
            });
        });
    }

    function endpointCreate(data) {
        $.ajax({
            url: API_URL + '/user',
            method: 'POST',
            data: data,
            success: function () {
                $('#signup-form')[0].reset();
                showMessage('#signup-message', 'Compte créé, vous pouvez vous connecter', false);
            },
            error: function (xhr) {
                let message = 'Erreur serveur, réessayez';
                if (xhr.status === 400) message = 'Tous les champs obligatoires doivent être remplis';
                if (xhr.status === 409) message = 'Ce pseudo ou cet email est déjà utilisé';
                showMessage('#signup-message', message, true);
            }
        });
    }

    function endpointUpdate(id, data) {
        authAjax({
            url: API_URL + '/user/' + id,
            method: 'PATCH',
            data: JSON.stringify(data),
            success: function () {
                $('#update-form')[0].reset();
                showMessage('#update-message', 'Profil mis à jour', false);
            },
            error: function (xhr) {
                let message = 'Erreur serveur, réessayez';
                if (xhr.status === 400) message = 'Aucun champ à modifier';
                if (xhr.status === 403) message = 'Vous ne pouvez modifier que votre propre compte';
                showMessage('#update-message', message, true);
            }
        });
    }

    function endpointDelete(id) {
        authAjax({
            url: API_URL + '/user/' + id,
            method: 'DELETE',
            success: function () {
                endSession('Votre compte a été supprimé', false);
            },
            error: function (xhr) {
                let message = 'Erreur serveur, réessayez';
                if (xhr.status === 403) message = 'Vous ne pouvez supprimer que votre propre compte';
                showMessage('#delete-message', message, true);
            }
        });
    }

    // ---------- 4. Motus ----------

    // Temps réel sans WebSocket : le client interroge l'API à intervalle régulier (polling)
    const POLL_INTERVAL = 1500; // ms
    let pollTimer = null;
    let pollPending = false;   // évite d'empiler les requêtes si le serveur est lent
    let currentGameId = null;  // null = pas de partie affichée, on est dans la liste des joueurs

    function startPolling() {
        if (pollTimer) return;
        poll();
        pollTimer = setInterval(poll, POLL_INTERVAL);
    }

    function stopPolling() {
        clearInterval(pollTimer);
        pollTimer = null;
        clearInterval(countdownTimer);
        currentGameId = null;
        lastPlayers = null;
        wasMyTurn = false;
        $('#back-to-lobby, #guess-form').hide();
    }

    function poll() {
        // Partie terminée : plus rien à attendre du serveur tant qu'on n'est pas revenu à la liste
        if (pollPending || $('#back-to-lobby').is(':visible')) return;
        pollPending = true;
        const done = function () { pollPending = false; };

        if (currentGameId) {
            // En partie : on récupère l'état pour voir le coup de l'adversaire
            authAjax({
                url: API_URL + '/game/' + currentGameId,
                method: 'GET',
                success: function (response) { done(); showGame(response.data); },
                error: done
            });
            return;
        }

        // Dans la liste : quelqu'un a-t-il lancé une partie contre moi ?
        authAjax({
            url: API_URL + '/game/current',
            method: 'GET',
            success: function (response) {
                if (response.data) {
                    done();
                    showGame(response.data);
                } else {
                    loadPlayers(done);
                }
            },
            error: done
        });
    }

    function loadPlayers(done) {
        authAjax({
            url: API_URL + '/players',
            method: 'GET',
            success: function (response) { done(); showPlayers(response.data); },
            error: done
        });
    }

    let lastPlayers = null;

    function showPlayers(players) {
        // On ne reconstruit la liste que si elle a changé, sinon un clic pourrait tomber pendant le remplacement
        const snapshot = JSON.stringify(players);
        if (snapshot === lastPlayers) return;
        lastPlayers = snapshot;

        const list = $('#players-list').empty();
        if (players.length === 0) {
            list.text('Aucun autre joueur disponible pour le moment.');
            return;
        }
        players.forEach(function (player) {
            // .text() et non .html() : un pseudo ne doit jamais pouvoir injecter du HTML
            $('<button type="button" class="player-chip">').text(player.pseudo)
                .attr('data-initial', player.pseudo.charAt(0).toUpperCase()) // lettre affichée sur la boule
                .on('click', function () { startGame(player.id); })
                .appendTo(list);
        });
    }

    function startGame(opponentId) {
        authAjax({
            url: API_URL + '/game',
            method: 'POST',
            data: {opponent_id: opponentId},
            success: function (response) {
                $('#lobby-message').text('');
                showGame(response.data);
            },
            error: function (xhr) {
                const message = xhr.status === 409 ? 'Ce joueur n\'est plus disponible' : 'Impossible de lancer la partie';
                showMessage('#lobby-message', message, true);
            }
        });
    }

    // Compte à rebours : le serveur donne seconds_left à chaque polling, on décompte entre deux réponses
    let secondsLeft = 0;
    let countdownTimer = null;
    let wasMyTurn = false;
    let shownGuesses = 0; // propositions déjà affichées, pour n'animer que les nouvelles

    function showGame(game) {
        if (game.id !== currentGameId) shownGuesses = game.guesses.length; // pas d'animation à l'ouverture
        currentGameId = game.id;
        $('#lobby, #account').hide();
        $('#game').show();

        const me = currentUserId();
        const opponent = game.player1.id === me ? game.player2 : game.player1;
        const pseudos = {};
        pseudos[game.player1.id] = game.player1.pseudo;
        pseudos[game.player2.id] = game.player2.pseudo;

        $('#game-title').text('Partie contre ' + opponent.pseudo);
        showGrid(game, pseudos, shownGuesses);
        shownGuesses = game.guesses.length;

        if (game.status !== 'in_progress') {
            showGameOver(game, me, opponent);
            return;
        }

        secondsLeft = game.seconds_left;
        const turnText = game.your_turn ? 'À vous de jouer' : 'Au tour de ' + opponent.pseudo;
        const showTurn = function () {
            $('#game-status').text(turnText + ', il reste ' + secondsLeft + ' s. Proposition '
                + (game.guesses.length + 1) + ' sur ' + game.max_guesses + '.')
                .toggleClass('urgent', game.your_turn && secondsLeft <= 3);
        };
        showTurn();
        clearInterval(countdownTimer);
        countdownTimer = setInterval(function () {
            if (secondsLeft > 0) secondsLeft--;
            showTurn();
        }, 1000);

        $('#guess-form').toggle(game.your_turn);
        if (game.your_turn && !wasMyTurn) {
            // Mon tour commence : champ vide et prêt à la saisie
            $('#guess-form')[0].reset();
            $('#guess-form input').trigger('focus');
        }
        wasMyTurn = game.your_turn;
    }

    // 8 lignes de 6 cases : les propositions jouées, puis des lignes vides
    function showGrid(game, pseudos, firstNewRow) {
        const grid = $('#grid').empty();
        for (let row = 0; row < game.max_guesses; row++) {
            const guess = game.guesses[row];
            const tr = $('<tr>').appendTo(grid);
            if (guess && row >= firstNewRow) tr.addClass('reveal'); // dévoilement lettre par lettre (CSS)
            $('<td class="player">').text(guess ? pseudos[guess.player_id] : '').appendTo(tr);

            if (guess && guess.word === null) {
                $('<td colspan="6" class="timeout">').text('temps écoulé').appendTo(tr);
                continue;
            }
            for (let i = 0; i < 6; i++) {
                const td = $('<td>').appendTo(tr);
                if (guess) td.text(guess.word[i]).addClass(guess.result[i]); // classe R, J ou B
            }
        }
    }

    function showGameOver(game, me, opponent) {
        clearInterval(countdownTimer);
        $('#game-status').removeClass('urgent');
        wasMyTurn = false;
        $('#guess-form').hide();
        $('#back-to-lobby').show();

        let text = 'Personne n\'a trouvé le mot ' + game.word + '.';
        if (game.status === 'won') {
            text = game.winner_id === me
                ? 'Bravo, vous avez trouvé le mot ' + game.word + ' !'
                : opponent.pseudo + ' a trouvé le mot ' + game.word + '.';
        }
        $('#game-status').text(text);
    }

    function leaveGame() {
        currentGameId = null;
        lastPlayers = null;
        $('#game, #back-to-lobby').hide();
        $('#guess-message').text('');
        $('#lobby, #account').show();
        poll();
    }

    function sendGuess(word) {
        authAjax({
            url: API_URL + '/game/' + currentGameId + '/guess',
            method: 'POST',
            data: {word: word},
            success: function (response) {
                $('#guess-message').text('');
                showGame(response.data);
            },
            error: function (xhr) {
                let message = 'Erreur serveur, réessayez';
                if (xhr.status === 400) message = 'Le mot doit contenir exactement 6 lettres, sans accent';
                if (xhr.status === 409) message = 'Ce n\'est pas votre tour';
                if (xhr.status === 422) message = 'Temps écoulé : votre tour est passé';
                showMessage('#guess-message', message, true);
            }
        });
    }

    // Au chargement : un token déjà présent = session en cours
    if (localStorage.getItem('accessToken')) {
        showLoggedIn();
    } else {
        showLoggedOut();
    }

    // 3.2 Envoi du formulaire d'authentification
    $('#login-form').on('submit', function (event) {
        event.preventDefault(); // empêche le rechargement de la page
        authenticate(this.email.value, this.password.value);
    });

    // 3.3 Envoi du formulaire de désauthentification
    $('#logout-form').on('submit', function (event) {
        event.preventDefault();
        deauthenticate();
    });

    // 3.4 Envoi du formulaire de création de compte
    $('#signup-form').on('submit', function (event) {
        event.preventDefault();
        endpointCreate($(this).serialize()); // "first_name=...&last_name=...&..."
    });

    // 3.5 Envoi du formulaire de modification (les champs vides sont ignorés par l'API)
    $('#update-form').on('submit', function (event) {
        event.preventDefault();
        endpointUpdate(currentUserId(), {
            pseudo: this.pseudo.value,
            gender: this.gender.value,
            avatar: this.avatar.value
        });
    });

    // 4. Proposition d'un mot (la longueur et les couleurs sont vérifiées par le serveur)
    $('#guess-form').on('submit', function (event) {
        event.preventDefault();
        sendGuess(this.word.value);
    });

    $('#back-to-lobby').on('click', leaveGame);

    // 3.6 Envoi du formulaire de suppression, après confirmation
    $('#delete-form').on('submit', function (event) {
        event.preventDefault();
        if (confirm('Supprimer définitivement votre compte ?')) {
            endpointDelete(currentUserId());
        }
    });
</script>
</body>
</html>
