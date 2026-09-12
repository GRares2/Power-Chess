#PowerChess API #Chess with Superpowers — Fully Implemented REST API in PHP Slim 4 with SQLite

#Description

PowerChess is a chess game where special powers randomly appear on the board. Pieces can collect and use these powers to modify the rules of movement. The project exposes a fully documented REST API using OpenAPI 3.0.1.

#Technologies

| Technology     | Version | Role                     |
| -------------- | ------- | ------------------------ |
| PHP            | 8.x     | Server                   |
| Slim           | 4.x     | REST framework / routing |
| SQLite         | 3       | Database                 |
| PDO            | —       | Database abstraction     |
| Apache (XAMPP) | 3.3.0   | Web server               |
| OpenAPI        | 3.0.1   | API specification        |
| Postman        | —       | API testing (Gherkin)    |
| HTML/JS        | —       | Interactive frontend UI  |

#Project Structure

```text
powerchess-server/
├── public/
│   ├── index.php              # Entry point — all API routes
│   ├── admin.php              # Administration panel
│   ├── game.html              # Game UI
│   ├── menu.html              # Main menu
│   ├── ui.html                # API Explorer
│   ├── powerchess.db          # SQLite database
│   └── .htaccess              # Apache routing
├── src/Controllers/
│   ├── BoardController.php
│   ├── GameController.php
│   ├── MoveController.php
│   ├── PieceController.php
│   ├── PlayerController.php
│   └── PowerController.php
├── vendor/                    # Composer dependencies
├── composer.json
├── powerchess-openapi.yaml    # OpenAPI 3.0.1 specification
└── PowerChess_Postman.json    # Postman collection with Gherkin scenarios
```

#Getting Started

## Option 1 — XAMPP

1. Copy the `powerchess-server` folder into `C:\xampp\htdocs\` or configure a Virtual Host.
2. Open the XAMPP Control Panel and start Apache.
3. Access: `http://powerchess.local`

### Option 2 — PHP Built-in Server

```bash
cd powerchess-server/public
php -S localhost:8080
```

Access: `http://localhost:8080`

#Endpoint

**Base URL:** `http://powerchess.local/menu.html` or `http://localhost:8080`

#Players

| Method | Endpoint              | Description          |
| ------ | --------------------- | -------------------- |
| POST   | `/players`            | Creates a new player |
| GET    | `/players/{playerId}` | Gets player details  |

#Games

| Method | Endpoint          | Description                            |
| ------ | ----------------- | -------------------------------------- |
| POST   | `/games`          | Creates a new game                     |
| GET    | `/games`          | Lists games (filter: `?status=active`) |
| GET    | `/games/{gameId}` | Gets game details                      |
| DELETE | `/games/{gameId}` | Deletes a game                         |

#Board & Pieces

| Method | Endpoint                           | Description                           |
| ------ | ---------------------------------- | ------------------------------------- |
| GET    | `/games/{gameId}/board`            | Gets the current 8x8 board state      |
| GET    | `/games/{gameId}/pieces`           | Lists pieces (filter: `?color=white`) |
| GET    | `/games/{gameId}/pieces/{pieceId}` | Gets piece details                    |
| PUT    | `/games/{gameId}/pieces/{pieceId}` | Promotes a pawn to a queen            |

#Moves

| Method | Endpoint                      | Description                                |
| ------ | ----------------------------- | ------------------------------------------ |
| GET    | `/games/{gameId}/moves`       | Gets move history                          |
| POST   | `/games/{gameId}/moves`       | Makes a move                               |
| GET    | `/games/{gameId}/moves/valid` | Gets valid moves for a piece (`?pieceId=`) |

#Powers

| Method | Endpoint                    | Description                     |
| ------ | --------------------------- | ------------------------------- |
| GET    | `/games/{gameId}/powers`    | Gets active powers on the board |
| POST   | `/powers/spawn`             | Generates a new power           |
| POST   | `/powers/{powerId}/collect` | Collects a power                |
| POST   | `/powers/{powerId}/use`     | Uses a collected power          |

#HTTP Status Codes

| Code | Meaning                                 |
| ---- | --------------------------------------- |
| 200  | OK — request successfully processed     |
| 201  | Created — resource successfully created |
| 204  | No Content — deletion successful        |
| 400  | Bad Request — invalid data              |
| 404  | Not Found — resource does not exist     |
| 409  | Conflict — invalid move                 |
| 500  | Internal Server Error                   |

#Special Powers (PowerType)

| Power         | Effect                                            | Status                |
| ------------- | ------------------------------------------------- | --------------------- |
| `teleport`    | Moves a piece to any unoccupied cell on the board | Implemented           |
| `shield`      | Protects a piece from being captured once         | Implemented           |
| `freeze`      | Freezes an opponent's piece for one turn          | Implemented           |
| `extra_move`  | Allows an additional move during the same turn    | **Under Development** |
| `double_jump` | Allows jumping over pieces (like a knight)        | **Under Development** |
| `rule_break`  | Allows a move that would normally be illegal      | **Under Development** |

#Postman Testing

1. Import `PowerChess_Postman.json` into Postman.
2. Import the environment variables and create a **New Environment** with:

   * `baseUrl` = `http://powerchess.local`
   * `gameId` = ID returned after `POST /games`
   * `playerId`, `pieceId`, `powerId` = IDs from the corresponding responses
3. Select **New Environment** from the dropdown in the top-right corner.
4. Run the requests in order: **Players → Games → Board → Pieces → Moves → Powers**

The scenarios are written in **Gherkin** format (`Given / When / Then`) with automatic validation through Postman Scripts.

#Admin Panel

Access `http://powerchess.local/admin.php` to view:

* Global statistics (players, games, moves)
* All registered players
* All games and their current status
* Recently generated powers
* Recent moves

The complete OpenAPI 3.0.1 specification can be found in the `powerchess-openapi.yaml` file.

It can be viewed interactively at [Swagger Editor](https://editor.swagger.io) by importing the file.
