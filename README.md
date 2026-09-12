#PowerChess API
#Șah cu superputeri — REST API complet implementat în PHP Slim 4 cu SQLite


#Descriere

PowerChess este un joc de șah în care apar aleatoriu puteri speciale pe tablă. Piesele le pot colecta și folosi pentru a modifica regulile de mișcare. Proiectul expune un REST API complet documentat cu OpenAPI 3.0.1.



#Tehnologii

 Tehnologie  Versiune  Rol 

  PHP  8.x  Server REST 
 Slim 4  4.x  Framework routing 
 SQLite  3  Baza de date 
PDO  Abstractizare BD 
Apache (XAMPP)  3.3.0  Server web producție 
OpenAPI  3.0.1  Specificație API 
Postman  Testare API (Gherkin) 
HTML/JS  UI frontend interactiv 



#Structura proiectului


powerchess-server/
├── public/
│   ├── index.php          # Entry point — toate rutele API
│   ├── admin.php          # Panou de administrare
│   ├── game.html          # UI joc
│   ├── menu.html          # Meniu principal
│   ├── ui.html            # API Explorer
│   ├── powerchess.db      # Baza de date SQLite
│   └── .htaccess          # Routing Apache
├── src/Controllers/
│   ├── BoardController.php
│   ├── GameController.php
│   ├── MoveController.php
│   ├── PieceController.php
│   ├── PlayerController.php
│   └── PowerController.php
├── vendor/                # Dependente Composer
├── composer.json
├── powerchess-openapi.yaml    # Specificatie OpenAPI 3.0.1
└── PowerChess_Postman.json    # Colectie Postman cu scenarii Gherkin
```

---

# Pornire

# Varianta 1 — XAMPP 

1. Copiază folderul `powerchess-server` în `C:\xampp\htdocs\` sau configurează un Virtual Host
2. Deschide XAMPP Control Panel și pornește Apache
3. Accesează: http://powerchess.local

### Varianta 2 — PHP built-in server

bash
cd powerchess-server/public
php -S localhost:8080


Accesează: http://localhost:8080

#Endpoint

**Base URL:** http://powerchess.local/menu.html sau http://localhost:8080

#Players
| Metodă | Endpoint | Descriere |
|---|---|---|
| POST | /players | Creează jucător nou |
| GET | /players/{playerId} | Detalii jucător |

#Games
| Metodă | Endpoint | Descriere |
|---|---|---|
| POST | `/games` | Creează partidă nouă |
| GET | `/games` | Listează partide (filtru: `?status=active`) |
| GET | `/games/{gameId}` | Detalii partidă |
| DELETE | `/games/{gameId}` | Șterge partidă |

#Board & Pieces
| Metodă | Endpoint | Descriere |
|---|---|---|
| GET | `/games/{gameId}/board` | Starea tablei 8x8 |
| GET | `/games/{gameId}/pieces` | Liste piese (filtru: `?color=white`) |
| GET | `/games/{gameId}/pieces/{pieceId}` | Detalii piesă |
| PUT | `/games/{gameId}/pieces/{pieceId}` | Promovează pion la regină |

#Moves
| Metodă | Endpoint | Descriere |
|---|---|---|
| GET | `/games/{gameId}/moves` | Istoric mutări |
| POST | `/games/{gameId}/moves` | Efectuează mutare |
| GET | `/games/{gameId}/moves/valid` | Mutări valide pentru o piesă (`?pieceId=`) |

#Powers
| Metodă | Endpoint | Descriere |
|---|---|---|
| GET | `/games/{gameId}/powers` | Puteri active pe tablă |
| POST | `/powers/spawn` | Generează putere nouă |
| POST | `/powers/{powerId}/collect` | Colectează putere |
| POST | `/powers/{powerId}/use` | Folosește putere colectată |



#Coduri HTTP

| Cod | Semnificație |
|---|---|
| 200 | OK — cerere procesată |
| 201 | Created — resursă creată |
| 204 | No Content — ștergere reușită |
| 400 | Bad Request — date invalide |
| 404 | Not Found — resursă inexistentă |
| 409 | Conflict — mutare invalidă |
| 500 | Internal Server Error |



#Puteri Speciale (PowerType)

| Putere | Efect |
|---|---|
| teleport | Mută piesa în orice celulă liberă de pe tablă |
| extra_move | Permite o mutare suplimentară în aceeași tură |
| shield | Protejează piesa de o captură (o singură dată) |
| freeze | Îngheață o piesă adversă timp de un tur |
| double_jump | Permite sărirea peste piese (ca un cal) |
| rule_break | Permite o mutare ilegală standard |


#Testare cu Postman

1. Importă `PowerChess_Postman.json` în Postman
2. Importă variabilele de mediu și setează **New Environment** cu:
   - `baseUrl` = `http://powerchess.local`
   - `gameId` = ID-ul returnat după POST /games
   - `playerId`, `pieceId`, `powerId` = IDs din răspunsuri
3. Selectează **New Environment** în dropdown-ul din dreapta sus
4. Rulează în ordine: Players → Games → Board → Pieces → Moves → Powers

Scenariile sunt scrise în format **Gherkin** (Given / When / Then) cu validare automată prin Postman Scripts.



#Admin Panel

Accesează http://powerchess.local/admin.php pentru a vizualiza:
- Statistici globale (jucători, partide, mutări)
- Toți jucătorii înregistrați
- Toate partidele cu statusul lor
- Puterile generate recent
- Mutările recente


Specificația completă OpenAPI 3.0.1 se găsește în fișierul `powerchess-openapi.yaml`.

Poate fi vizualizată interactiv la [https://editor.swagger.io](https://editor.swagger.io) prin import.
