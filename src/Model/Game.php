<?php

class Game
{
    private ?PDO $conn = null;

    public function __construct()
    {
        try {
            $this->conn = Database::getInstance()->getConnection();
        } catch (Exception $e) {
            logWithDate('DB connection failed', $e->getMessage());
        }
    }

    public function findRandomWord(): ?string
    {
        try {
            throwDbNullConnection($this->conn);

            $stmt = $this->conn->query('SELECT word FROM word ORDER BY RAND() LIMIT 1');
            $row = $stmt->fetch();

            return $row ? $row['word'] : null;
        } catch (PDOException|Exception $e) {
            logWithDate('Query failed', $e->getMessage());
            return null;
        }
    }

    public function create(int $player1Id, int $player2Id, string $word): ?int
    {
        try {
            throwDbNullConnection($this->conn);

            // Player 1 (who starts the game) plays first
            $query = 'INSERT INTO game (player1_id, player2_id, word, current_player_id, turn_started_at) ';
            $query .= 'VALUES (:player1_id, :player2_id, :word, :player1_id, NOW())';

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':player1_id', $player1Id, PDO::PARAM_INT);
            $stmt->bindParam(':player2_id', $player2Id, PDO::PARAM_INT);
            $stmt->bindParam(':word', $word);
            $stmt->execute();

            return (int) $this->conn->lastInsertId();
        } catch (PDOException|Exception $e) {
            logWithDate('Query failed', $e->getMessage());
            return null;
        }
    }

    // The elapsed time is computed by MySQL so that PHP and MySQL clocks never disagree
    public function findById(int $id): mixed
    {
        try {
            throwDbNullConnection($this->conn);

            $query = 'SELECT game.*, ';
            $query .= 'TIMESTAMPDIFF(SECOND, turn_started_at, NOW()) AS turn_elapsed, ';
            $query .= 'p1.pseudo AS player1_pseudo, p2.pseudo AS player2_pseudo ';
            $query .= 'FROM game ';
            $query .= 'JOIN user p1 ON p1.id = game.player1_id ';
            $query .= 'JOIN user p2 ON p2.id = game.player2_id ';
            $query .= 'WHERE game.id = :id';

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch();
        } catch (PDOException|Exception $e) {
            logWithDate('Query failed', $e->getMessage());
            return null;
        }
    }

    public function findInProgressIdByPlayer(int $userId): ?int
    {
        try {
            throwDbNullConnection($this->conn);

            $query = "SELECT id FROM game WHERE status = 'in_progress' ";
            $query .= 'AND (player1_id = :id OR player2_id = :id) LIMIT 1';

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch();

            return $row ? (int) $row['id'] : null;
        } catch (PDOException|Exception $e) {
            logWithDate('Query failed', $e->getMessage());
            return null;
        }
    }

    public function findGuesses(int $gameId): ?array
    {
        try {
            throwDbNullConnection($this->conn);

            $query = 'SELECT player_id, word, result FROM guess WHERE game_id = :game_id ORDER BY id';

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll();
        } catch (PDOException|Exception $e) {
            logWithDate('Query failed', $e->getMessage());
            return null;
        }
    }

    // $word and $result are NULL when the player ran out of time
    public function addGuess(int $gameId, int $playerId, ?string $word, ?string $result): void
    {
        try {
            throwDbNullConnection($this->conn);

            $query = 'INSERT INTO guess (game_id, player_id, word, result) VALUES (:game_id, :player_id, :word, :result)';

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
            $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
            $stmt->bindParam(':word', $word);
            $stmt->bindParam(':result', $result);
            $stmt->execute();
        } catch (PDOException|Exception $e) {
            logWithDate('Query failed', $e->getMessage());
        }
    }

    public function setCurrentPlayer(int $gameId, int $playerId): void
    {
        try {
            throwDbNullConnection($this->conn);

            $query = 'UPDATE game SET current_player_id = :player_id, turn_started_at = NOW() WHERE id = :id';

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':player_id', $playerId, PDO::PARAM_INT);
            $stmt->bindParam(':id', $gameId, PDO::PARAM_INT);
            $stmt->execute();
        } catch (PDOException|Exception $e) {
            logWithDate('Query failed', $e->getMessage());
        }
    }

    // Gives the turn to $toId only if $fromId's turn has really expired.
    // The conditions are in the UPDATE itself, so two simultaneous requests cannot both pass the turn.
    public function passTurnIfExpired(int $gameId, int $fromId, int $toId, int $seconds): bool
    {
        try {
            throwDbNullConnection($this->conn);

            $query = 'UPDATE game SET current_player_id = :to_id, turn_started_at = NOW() ';
            $query .= "WHERE id = :id AND status = 'in_progress' AND current_player_id = :from_id ";
            $query .= 'AND TIMESTAMPDIFF(SECOND, turn_started_at, NOW()) > :seconds';

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':to_id', $toId, PDO::PARAM_INT);
            $stmt->bindParam(':id', $gameId, PDO::PARAM_INT);
            $stmt->bindParam(':from_id', $fromId, PDO::PARAM_INT);
            $stmt->bindParam(':seconds', $seconds, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->rowCount() === 1;
        } catch (PDOException|Exception $e) {
            logWithDate('Query failed', $e->getMessage());
            return false;
        }
    }

    // $status: 'won' (with the winner) or 'draw'
    public function finish(int $gameId, string $status, ?int $winnerId): void
    {
        try {
            throwDbNullConnection($this->conn);

            $query = 'UPDATE game SET status = :status, winner_id = :winner_id WHERE id = :id';

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':winner_id', $winnerId, PDO::PARAM_INT);
            $stmt->bindParam(':id', $gameId, PDO::PARAM_INT);
            $stmt->execute();
        } catch (PDOException|Exception $e) {
            logWithDate('Query failed', $e->getMessage());
        }
    }
}
