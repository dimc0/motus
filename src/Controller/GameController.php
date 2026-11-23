<?php

require_once __DIR__ . '/../Model/Game.php';
require_once __DIR__ . '/../Model/User.php';
require_once __DIR__ . '/../../helpers/motus_helper.php';

class GameController
{
    private const int WORD_LENGTH = 6;
    private const int TURN_SECONDS = 8;
    private const int MAX_GUESSES = 8;

    private Game $game;
    private User $user;

    public function __construct()
    {
        $this->game = new Game();
        $this->user = new User();
    }

    // POST /api/game : the connected user (player 1) starts a game against opponent_id
    public function create($userId): void
    {
        $userId = (int) $userId;
        $opponentId = (int) ($_POST['opponent_id'] ?? 0);

        if (!$opponentId || $opponentId === $userId) {
            sendResponseCustom('Invalid opponent', null, 'Error', 400);
            exit(1);
        }

        if ($this->game->findInProgressIdByPlayer($userId)) {
            sendResponseCustom('You already have a game in progress', null, 'Error', 409);
            exit(1);
        }

        // The opponent must be connected and without a game in progress
        $availableIds = array_column($this->user->findAvailablePlayers($userId) ?? [], 'id');
        if (!in_array($opponentId, $availableIds)) {
            sendResponseCustom('Player not available', null, 'Error', 409);
            exit(1);
        }

        $word = $this->game->findRandomWord();
        $gameId = $word ? $this->game->create($userId, $opponentId, $word) : null;
        if (!$gameId) {
            sendResponse500();
            exit(1);
        }

        sendResponseCustom('Game created', $this->buildState($gameId, $userId), 'Success', 201);
    }

    // GET /api/game/current : the game in progress of the connected user, or null
    // (this is how player 2 finds out that someone started a game against him)
    public function current($userId): void
    {
        $gameId = $this->game->findInProgressIdByPlayer((int) $userId);
        if ($gameId) {
            $this->applyTimeout($this->game->findById($gameId));
        }

        sendResponseCustom('Current game', $gameId ? $this->buildState($gameId, (int) $userId) : null);
    }

    // GET /api/game/{id} : state of a game (grid, turn, time left, result)
    public function show($userId, $gameId): void
    {
        $this->findGameOfPlayer((int) $gameId, (int) $userId);

        sendResponseCustom('Game state', $this->buildState((int) $gameId, (int) $userId));
    }

    // POST /api/game/{id}/guess : the player whose turn it is proposes a word
    public function guess($userId, $gameId): void
    {
        $userId = (int) $userId;
        $gameId = (int) $gameId;
        $game = $this->findGameOfPlayer($gameId, $userId);

        if ($game['status'] !== 'in_progress') {
            sendResponseCustom('Game is over', null, 'Error', 409);
            exit(1);
        }

        if ((int) $game['current_player_id'] !== $userId) {
            // Either it is the opponent's turn, or this player's 8 seconds have just run out
            sendResponseCustom('Not your turn', null, 'Error', 409);
            exit(1);
        }

        // A word of the wrong length is refused and does not count as a guess
        $word = strtoupper(trim($_POST['word'] ?? ''));
        if (!preg_match('/^[A-Z]{' . self::WORD_LENGTH . '}$/', $word)) {
            sendResponseCustom('The word must contain exactly ' . self::WORD_LENGTH . ' letters (A-Z)', null, 'Error', 400);
            exit(1);
        }

        $result = computeGuessResult($game['word'], $word);
        $this->game->addGuess($gameId, $userId, $word, $result);

        if ($result === str_repeat('R', self::WORD_LENGTH)) {
            $this->game->finish($gameId, 'won', $userId);
        } elseif (!$this->finishIfNoGuessLeft($gameId)) {
            $this->game->setCurrentPlayer($gameId, $this->opponentOf($game, $userId));
        }

        sendResponseCustom('Guess recorded', $this->buildState($gameId, $userId));
    }

    // Stops the request with 404 / 403 if the game does not exist or the user is not one of its players
    private function findGameOfPlayer(int $gameId, int $userId): array
    {
        $game = $this->game->findById($gameId);

        if (!$game) {
            sendResponse404();
            exit(1);
        }

        if ($userId !== (int) $game['player1_id'] && $userId !== (int) $game['player2_id']) {
            sendResponse403();
            exit(1);
        }

        return $this->applyTimeout($game);
    }

    // There is no background task in PHP: the 8 seconds are checked each time a player asks
    // for the game (polling every ~1.5 s). An expired turn counts as an empty guess and the
    // turn goes to the opponent. TURN_SECONDS is exceeded by about 1 s to absorb network latency.
    private function applyTimeout(array $game): array
    {
        if ($game['status'] !== 'in_progress' || (int) $game['turn_elapsed'] <= self::TURN_SECONDS) {
            return $game;
        }

        $gameId = (int) $game['id'];
        $currentId = (int) $game['current_player_id'];

        if ($this->game->passTurnIfExpired($gameId, $currentId, $this->opponentOf($game, $currentId), self::TURN_SECONDS)) {
            $this->game->addGuess($gameId, $currentId, null, null);
            $this->finishIfNoGuessLeft($gameId);
        }

        return $this->game->findById($gameId);
    }

    // After the 8th guess without finding the word, nobody wins
    private function finishIfNoGuessLeft(int $gameId): bool
    {
        if (count($this->game->findGuesses($gameId) ?? []) < self::MAX_GUESSES) {
            return false;
        }

        $this->game->finish($gameId, 'draw', null);
        return true;
    }

    private function opponentOf(array $game, int $userId): int
    {
        return $userId === (int) $game['player1_id'] ? (int) $game['player2_id'] : (int) $game['player1_id'];
    }

    // What the clients receive: never the secret word while the game is in progress
    private function buildState(int $gameId, int $userId): array
    {
        $game = $this->game->findById($gameId);
        $inProgress = $game['status'] === 'in_progress';

        return [
            'id' => (int) $game['id'],
            'player1' => ['id' => (int) $game['player1_id'], 'pseudo' => $game['player1_pseudo']],
            'player2' => ['id' => (int) $game['player2_id'], 'pseudo' => $game['player2_pseudo']],
            'status' => $game['status'],
            'winner_id' => $game['winner_id'] !== null ? (int) $game['winner_id'] : null,
            'current_player_id' => (int) $game['current_player_id'],
            'your_turn' => $inProgress && (int) $game['current_player_id'] === $userId,
            'seconds_left' => $inProgress ? max(0, self::TURN_SECONDS - (int) $game['turn_elapsed']) : 0,
            'max_guesses' => self::MAX_GUESSES,
            'guesses' => $this->game->findGuesses($gameId) ?? [],
            'word' => $inProgress ? null : $game['word'],
        ];
    }
}
