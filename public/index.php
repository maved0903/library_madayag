<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

    require '../src/vendor/autoload.php';

    session_start(); // Start session to keep track of used tokens

    // Array to keep track of used tokens
    if (!isset($_SESSION['used_tokens'])) {
        $_SESSION['used_tokens'] = [];
    }

    $app = new \Slim\App;

    // Middleware to validate JWT token and check if it's been used
    $authMiddleware = function (Request $request, Response $response, callable $next) {
        $authHeader = $request->getHeader('Authorization');
    
        if ($authHeader) {
            $token = str_replace('Bearer ', '', $authHeader[0]);
    
            // Check if token has been used
            if (in_array($token, $_SESSION['used_tokens'])) {
                return $response->withStatus(401)->write(json_encode(array("status" => "fail", "data" => array("title" => "Token has already been used"))));
            }
    
            try {
                $decoded = JWT::decode($token, new Key('server_hack', 'HS256'));
                $request = $request->withAttribute('decoded', $decoded);
            } catch (\Exception $e) {
                return $response->withStatus(401)->write(json_encode(array("status" => "fail", "data" => array("title" => "Unauthorized: " . $e->getMessage()))));
            }
    
            // Revoke the token after using it
            $_SESSION['used_tokens'][] = $token;
        } else {
            return $response->withStatus(401)->write(json_encode(array("status" => "fail", "data" => array("title" => "Token not provided"))));
        }
    
        return $next($request, $response);
    };

    // User registration
    $app->post('/user/register', function (Request $request, Response $response, array $args) {
        $data = json_decode($request->getBody());

        $usr = trim($data->username);
        $pass = trim($data->password);

        $servername = "localhost";
        $username = "root";
        $password = "";
        $dbname = "library";

        try {
            $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Check if username already exists
            $stmt = $conn->prepare("SELECT * FROM users WHERE username = :username");
            $stmt->execute([':username' => $usr]);

            if ($stmt->rowCount() > 0) {
                $response->getBody()->write(json_encode(array("status" => "fail", "data" => "Username already exists")));
                return $response;
            }

            $sql = "INSERT INTO users (username, password) VALUES (:username, :password)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':username' => $usr, ':password' => hash('SHA256', $pass)]);

            $response->getBody()->write(json_encode(array("status" => "success", "data" => null)));

        } catch (PDOException $e) {
            $response->getBody()->write(json_encode(array("status" => "fail", "data" => array("title" => $e->getMessage()))));
        }
        return $response;
    });

    // User authentication (acts as log-in too)
    $app->post('/user/authenticate', function (Request $request, Response $response, array $args) {
        $data = json_decode($request->getBody());

        if (!isset($data->username) || !isset($data->password)) {
            return $response->getBody()->write(json_encode(array("status" => "fail", "data" => array("title" => "Invalid input data"))));
        }

        $usr = trim($data->username);
        $pass = trim($data->password);

        $servername = "localhost";
        $db_username = "root";
        $db_password = "";
        $dbname = "library";

        try {
            $conn = new PDO("mysql:host=$servername;dbname=$dbname", $db_username, $db_password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $checkUserStmt = $conn->prepare("SELECT * FROM users WHERE username = :username");
            $checkUserStmt->execute([':username' => $usr]);

            if ($checkUserStmt->rowCount() == 0) {
                return $response->getBody()->write(json_encode(array("status" => "fail", "data" => array("title" => "Incorrect username"))));
            }

            $checkPassStmt = $conn->prepare("SELECT * FROM users WHERE username = :username AND password = :password");
            $checkPassStmt->execute([':username' => $usr, ':password' => hash('SHA256', $pass)]);

            if ($checkPassStmt->rowCount() == 0) {
                return $response->getBody()->write(json_encode(array("status" => "fail", "data" => array("title" => "Incorrect password"))));
            }

            // If username and password are correct, generate the JWT token
            $data = $checkPassStmt->fetch(PDO::FETCH_ASSOC);
            $key = 'server_hack';
            $iat = time();
            $payload = [
                'iss' => 'http://library.org',
                'aud' => 'http://library.com',
                'iat' => $iat,
                'exp' => $iat + 3600, 
                'data' => array("userId" => $data['userId'])
            ];
            $jwt = JWT::encode($payload, $key, 'HS256');

            return $response->getBody()->write(json_encode(array("status" => "success", "token" => $jwt, "data" => null)));

        } catch (PDOException $e) {
            return $response->getBody()->write(json_encode(array("status" => "fail", "data" => array("title" => $e->getMessage()))));
        }
    });

    // Updating user account 
    $app->put('/user/update', function (Request $request, Response $response, array $args) {
        $data = json_decode($request->getBody());

        // Check if required fields are provided
        if (!isset($data->username) || !isset($data->new_username) || !isset($data->new_password)) {
            return $response->getBody()->write(json_encode(array("status" => "fail", "data" => "Invalid input data")));
        }

        $servername = "localhost";
        $db_username = "root";
        $db_password = "";
        $dbname = "library";

        try {
            $conn = new PDO("mysql:host=$servername;dbname=$dbname", $db_username, $db_password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Decode token to get userId from JWT token
            $userId = $request->getAttribute('decoded')->data->userId;
            
            $stmt = $conn->prepare("SELECT * FROM users WHERE userId = :userId AND username = :username");
            $stmt->execute([
                ':userId' => $userId,
                ':username' => $data->username 
            ]);

            if ($stmt->rowCount() == 0) {
                return $response->getBody()->write(json_encode(array("status" => "fail", "data" => "Invalid username")));
            }

            $updateStmt = $conn->prepare("UPDATE users SET username = :new_username, password = :new_password WHERE userId = :userId");
            $updateStmt->execute([
                ':new_username' => $data->new_username,          
                ':new_password' => hash('SHA256', $data->new_password), 
                ':userId' => $userId 
            ]);

            // Revoke the current token by adding it to the used tokens list
            $token = str_replace('Bearer ', '', $request->getHeader('Authorization')[0]);
            $_SESSION['used_tokens'][] = $token;

            // Generate a new token
            $key = 'server_hack';
            $iat = time();
            $payload = [
                'iss' => 'http://library.org',
                'aud' => 'http://library.com',
                'iat' => $iat,
                'exp' => $iat + 3600, 
                'data' => array("userId" => $userId)
            ];
            $new_jwt = JWT::encode($payload, $key, 'HS256');

            return $response->getBody()->write(json_encode(array("status" => "success", "token" => $new_jwt, "data" => null)));

        } catch (PDOException $e) {
            return $response->getBody()->write(json_encode(array("status" => "fail", "data" => array("title" => $e->getMessage()))));
        }
    })->add($authMiddleware);

    // Deleting user account 
    $app->delete('/user/delete', function (Request $request, Response $response, array $args) {
        $data = json_decode($request->getBody());

        if (!isset($data->username)) {
            return $response->getBody()->write(json_encode(array("status" => "fail", "data" => "Invalid input data")));
        }

        $servername = "localhost";
        $db_username = "root";
        $db_password = "";
        $dbname = "library";

        try {
            $conn = new PDO("mysql:host=$servername;dbname=$dbname", $db_username, $db_password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $userId = $request->getAttribute('decoded')->data->userId;

            $stmt = $conn->prepare("SELECT * FROM users WHERE userId = :userId AND username = :username");
            $stmt->execute([
                ':userId' => $userId,
                ':username' => $data->username
            ]);

            if ($stmt->rowCount() == 0) {
                return $response->getBody()->write(json_encode(array("status" => "fail", "data" => "Invalid username")));
            }

            $deleteStmt = $conn->prepare("DELETE FROM users WHERE userId = :userId");
            $deleteStmt->execute([':userId' => $userId]);

            $token = str_replace('Bearer ', '', $request->getHeader('Authorization')[0]);
            $_SESSION['used_tokens'][] = $token;

            $key = 'server_hack';
            $iat = time();
            $payload = [
                'iss' => 'http://library.org',
                'aud' => 'http://library.com',
                'iat' => $iat,
                'exp' => $iat + 3600, 
                'data' => array("userId" => $userId)
            ];
            $new_jwt = JWT::encode($payload, $key, 'HS256');

            return $response->getBody()->write(json_encode(array("status" => "success", "token" => $new_jwt, "data" => "User account deleted")));

        } catch (PDOException $e) {
            return $response->getBody()->write(json_encode(array("status" => "fail", "data" => array("title" => $e->getMessage()))));
        }
    })->add($authMiddleware);

    // Display users
    $app->get('/user/display', function (Request $request, Response $response, array $args) {
        $queryParams = $request->getQueryParams();
        $userId = $queryParams['userId'] ?? null; 

        $servername = "localhost";
        $db_username = "root";
        $password = "";
        $dbname = "library";

        try {
            $conn = new PDO("mysql:host=$servername;dbname=$dbname", $db_username, $password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Decode token to get userId from JWT token 
            $tokenUserId = $request->getAttribute('decoded')->data->userId;

            // Fetch specific user if userId is provided
            if ($userId) {
                $stmt = $conn->prepare("SELECT userId, username FROM users WHERE userId = :userId");
                $stmt->execute([':userId' => $userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    // Revoke the current token
                    $token = str_replace('Bearer ', '', $request->getHeader('Authorization')[0]);
                    $_SESSION['used_tokens'][] = $token;

                    // Generate a new token
                    $key = 'server_hack';
                    $iat = time();
                    $payload = [
                        'iss' => 'http://library.org',
                        'aud' => 'http://library.com',
                        'iat' => $iat,
                        'exp' => $iat + 3600, 
                        'data' => array("userId" => $tokenUserId)
                    ];
                    $new_jwt = JWT::encode($payload, $key, 'HS256');

                    return $response->getBody()->write(json_encode(array("status" => "success", "data" => $user, "token" => $new_jwt)));
                } else {
                    return $response->getBody()->write(json_encode(array("status" => "fail", "data" => "User not found")));
                }
            } else {
                $stmt = $conn->prepare("SELECT userId, username FROM users");
                $stmt->execute();
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (count($users) > 0) {
                    // Revoke the current token
                    $token = str_replace('Bearer ', '', $request->getHeader('Authorization')[0]);
                    $_SESSION['used_tokens'][] = $token;

                    // Generate a new token
                    $key = 'server_hack';
                    $iat = time();
                    $payload = [
                        'iss' => 'http://library.org',
                        'aud' => 'http://library.com',
                        'iat' => $iat,
                        'exp' => $iat + 3600, 
                        'data' => array("userId" => $tokenUserId)
                    ];
                    $new_jwt = JWT::encode($payload, $key, 'HS256');

                    return $response->getBody()->write(json_encode(array("status" => "success", "token" => $new_jwt, "data" => $users,)));
                } else {
                    return $response->getBody()->write(json_encode(array("status" => "fail", "data" => "No users found")));
                }
            }

        } catch (PDOException $e) {
            return $response->getBody()->write(json_encode(array("status" => "fail", "data" => array("title" => $e->getMessage()))));
        }
    })->add($authMiddleware);


    // Add author's name 
    $app->post('/author/add', function (Request $request, Response $response, array $args) {
        $data = json_decode($request->getBody());

        if (!isset($data->name) || empty($data->name)) {
            return $response->getBody()->write(json_encode(array("status" => "fail", "data" => "Invalid input data")));
        }

        $name = trim($data->name);

        $servername = "localhost";
        $db_username = "root";
        $password = "";
        $dbname = "library";

        try {
            $conn = new PDO("mysql:host=$servername;dbname=$dbname", $db_username, $password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $tokenUserId = $request->getAttribute('decoded')->data->userId;

            $stmt = $conn->prepare("SELECT COUNT(*) FROM authors WHERE name = :name");
            $stmt->execute([':name' => $name]);
            $count = $stmt->fetchColumn();

            if ($count > 0) {
                return $response->getBody()->write(json_encode(array("status" => "fail", "data" => "Author's name already exists")));
            }

            $stmt = $conn->prepare("INSERT INTO authors (name) VALUES (:name)");
            $stmt->execute([':name' => $name]);

            // Revoke the current token
            $token = str_replace('Bearer ', '', $request->getHeader('Authorization')[0]);
            $_SESSION['used_tokens'][] = $token;

            // Generate a new token
            $key = 'server_hack';
            $iat = time();
            $payload = [
                'iss' => 'http://library.org',
                'aud' => 'http://library.com',
                'iat' => $iat,
                'exp' => $iat + 3600, 
                'data' => array("userId" => $tokenUserId)
            ];
            $new_jwt = JWT::encode($payload, $key, 'HS256');

            return $response->getBody()->write(json_encode(array("status" => "success", "token" => $new_jwt, "data" => null)));

        } catch (PDOException $e) {
            return $response->getBody()->write(json_encode(array("status" => "fail", "data" => array("title" => $e->getMessage()))));
        }
    })->add($authMiddleware);

    // Update author's name 
    $app->put('/author/update', function (Request $request, Response $response, array $args) {
        $data = json_decode($request->getBody());

        if (!isset($data->old_name) || !isset($data->new_name) || empty($data->old_name) || empty($data->new_name)) {
            return $response->getBody()->write(json_encode(array("status" => "fail", "data" => "Both 'old_name' and 'new_name' must be provided")));
        }

        $oldName = trim($data->old_name);
        $newName = trim($data->new_name);

        $servername = "localhost";
        $db_username = "root";
        $password = "";
        $dbname = "library";

        try {
            $conn = new PDO("mysql:host=$servername;dbname=$dbname", $db_username, $password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Decode token to get userId from JWT token (for token rotation)
            $tokenUserId = $request->getAttribute('decoded')->data->userId;

            $stmt = $conn->prepare("SELECT COUNT(*) FROM authors WHERE name = :old_name");
            $stmt->execute([':old_name' => $oldName]);
            $oldCount = $stmt->fetchColumn();

            if ($oldCount == 0) {
                return $response->getBody()->write(json_encode(array("status" => "fail", "data" => "Old author's name does not exist")));
            }

            $stmt = $conn->prepare("SELECT COUNT(*) FROM authors WHERE name = :new_name");
            $stmt->execute([':new_name' => $newName]);
            $newCount = $stmt->fetchColumn();

            if ($newCount > 0) {
                return $response->getBody()->write(json_encode(array("status" => "fail", "data" => "Author's new name already exists")));
            }

            $stmt = $conn->prepare("UPDATE authors SET name = :new_name WHERE name = :old_name");
            $stmt->execute([':new_name' => $newName, ':old_name' => $oldName]);

            // Revoke the current token
            $token = str_replace('Bearer ', '', $request->getHeader('Authorization')[0]);
            $_SESSION['used_tokens'][] = $token;

            // Generate a new token
            $key = 'server_hack';
            $iat = time();
            $payload = [
                'iss' => 'http://library.org',
                'aud' => 'http://library.com',
                'iat' => $iat,
                'exp' => $iat + 3600, 
                'data' => array("userId" => $tokenUserId)
            ];
            $new_jwt = JWT::encode($payload, $key, 'HS256');

            return $response->getBody()->write(json_encode(array("status" => "success", "token" => $new_jwt, "data" => null)));

        } catch (PDOException $e) {
            return $response->getBody()->write(json_encode(array("status" => "fail", "data" => array("title" => $e->getMessage()))));
        }
    })->add($authMiddleware);

    // Delete author 
    $app->delete('/author/delete', function (Request $request, Response $response, array $args) {
        $data = json_decode($request->getBody());

        if (!isset($data->name) || empty($data->name)) {
            return $response->getBody()->write(json_encode(array("status" => "fail", "data" => "Invalid input data")));
        }

        $name = trim($data->name);

        $servername = "localhost";
        $db_username = "root";
        $password = "";
        $dbname = "library";

        try {
            $conn = new PDO("mysql:host=$servername;dbname=$dbname", $db_username, $password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Decode token to get userId from JWT token (for token rotation)
            $tokenUserId = $request->getAttribute('decoded')->data->userId;

            $stmt = $conn->prepare("SELECT COUNT(*) FROM authors WHERE name = :name");
            $stmt->execute([':name' => $name]);
            $count = $stmt->fetchColumn();

            if ($count == 0) {
                return $response->getBody()->write(json_encode(array("status" => "fail", "data" => "Author not found")));
            }

            $stmt = $conn->prepare("DELETE FROM authors WHERE name = :name");
            $stmt->execute([':name' => $name]);

            // Revoke the current token
            $token = str_replace('Bearer ', '', $request->getHeader('Authorization')[0]);
            $_SESSION['used_tokens'][] = $token;

            // Generate a new token
            $key = 'server_hack';
            $iat = time();
            $payload = [
                'iss' => 'http://library.org',
                'aud' => 'http://library.com',
                'iat' => $iat,
                'exp' => $iat + 3600, 
                'data' => array("userId" => $tokenUserId)
            ];
            $new_jwt = JWT::encode($payload, $key, 'HS256');

            return $response->getBody()->write(json_encode(array("status" => "success", "token" => $new_jwt, "data" => null)));

        } catch (PDOException $e) {
            return $response->getBody()->write(json_encode(array("status" => "fail", "data" => array("title" => $e->getMessage()))));
        }
    })->add($authMiddleware);
    
    // Display author's name 
    $app->get('/author/display', function (Request $request, Response $response, array $args) {
        $queryParams = $request->getQueryParams();
        $name = $queryParams['name'] ?? null; 

        $servername = "localhost";
        $db_username = "root";
        $password = "";
        $dbname = "library";

        try {
            $conn = new PDO("mysql:host=$servername;dbname=$dbname", $db_username, $password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Decode token to get userId from JWT token (for token rotation)
            $tokenUserId = $request->getAttribute('decoded')->data->userId;

            if ($name) {
                $stmt = $conn->prepare("SELECT * FROM authors WHERE name = :name");
                $stmt->execute([':name' => $name]);
                $author = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($author) {
                    // Revoke the current token
                    $token = str_replace('Bearer ', '', $request->getHeader('Authorization')[0]);
                    $_SESSION['used_tokens'][] = $token;

                    // Generate a new token
                    $key = 'server_hack';
                    $iat = time();
                    $payload = [
                        'iss' => 'http://library.org',
                        'aud' => 'http://library.com',
                        'iat' => $iat,
                        'exp' => $iat + 3600, 
                        'data' => array("userId" => $tokenUserId)
                    ];
                    $new_jwt = JWT::encode($payload, $key, 'HS256');

                    return $response->getBody()->write(json_encode(array("status" => "success", "data" => $author, "token" => $new_jwt)));
                } else {
                    return $response->getBody()->write(json_encode(array("status" => "fail", "data" => "Author not found")));
                }
            } else {
                // Fetch all authors
                $stmt = $conn->prepare("SELECT * FROM authors");
                $stmt->execute();
                $authors = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (count($authors) > 0) {
                    // Revoke the current token
                    $token = str_replace('Bearer ', '', $request->getHeader('Authorization')[0]);
                    $_SESSION['used_tokens'][] = $token;

                    // Generate a new token
                    $key = 'server_hack';
                    $iat = time();
                    $payload = [
                        'iss' => 'http://library.org',
                        'aud' => 'http://library.com',
                        'iat' => $iat,
                        'exp' => $iat + 3600, // Token valid for 1 hour
                        'data' => array("userId" => $tokenUserId)
                    ];
                    $new_jwt = JWT::encode($payload, $key, 'HS256');

                    // Return success response with all authors and the new token
                    return $response->getBody()->write(json_encode(array("status" => "success",  "token" => $new_jwt, "data" => $authors)));
                } else {
                    return $response->getBody()->write(json_encode(array("status" => "fail", "data" => "No authors found")));
                }
            }

        } catch (PDOException $e) {
            return $response->getBody()->write(json_encode(array("status" => "fail", "data" => array("title" => $e->getMessage()))));
        }
    })->add($authMiddleware);


    //Add book
    $app->post('/book/add', function (Request $request, Response $response, array $args) {
        $data = json_decode($request->getBody());
    
        if (!isset($data->title) || !isset($data->authorId) || empty($data->title) || empty($data->authorId)) {
            return $response->withStatus(400)->getBody()->write(json_encode(array("status" => "fail", "data" => "Invalid input data. Both 'title' and 'authorId' must be provided.")));
        }
    
        $title = trim($data->title);
        $authorId = intval($data->authorId);
    
        $servername = "localhost";
        $db_username = "root";
        $password = "";
        $dbname = "library";
    
        try {
            $conn = new PDO("mysql:host=$servername;dbname=$dbname", $db_username, $password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
            $tokenUserId = $request->getAttribute('decoded')->data->userId;
    
            $authorStmt = $conn->prepare("SELECT COUNT(*) FROM authors WHERE authorId = :authorId");
            $authorStmt->execute([':authorId' => $authorId]);
            $authorCount = $authorStmt->fetchColumn();
    
            if ($authorCount == 0) {
                return $response->withStatus(404)->getBody()->write(json_encode(array("status" => "fail", "data" => "Author ID not found")));
            }

            $bookStmt = $conn->prepare("SELECT COUNT(*) FROM books WHERE title = :title");
            $bookStmt->execute([':title' => $title]);
            $bookCount = $bookStmt->fetchColumn();
    
            if ($bookCount > 0) {
                return $response->withStatus(409)->getBody()->write(json_encode(array("status" => "fail", "data" => "Book with this title already exists")));
            }
            $stmt = $conn->prepare("INSERT INTO books (title, authorId) VALUES (:title, :authorId)");
            $stmt->execute([':title' => $title, ':authorId' => $authorId]);
    
            // Revoke the current token
            $token = str_replace('Bearer ', '', $request->getHeader('Authorization')[0]);
            $_SESSION['used_tokens'][] = $token;
    
            // Generate a new token
            $key = 'server_hack';
            $iat = time();
            $payload = [
                'iss' => 'http://library.org',
                'aud' => 'http://library.com',
                'iat' => $iat,
                'exp' => $iat + 3600, 
                'data' => array("userId" => $tokenUserId)
            ];
            $new_jwt = JWT::encode($payload, $key, 'HS256');

            return $response->withStatus(201)->getBody()->write(json_encode(array("status" => "success", "data" => "Book added successfully", "token" => $new_jwt)));
    
        } catch (PDOException $e) {
            return $response->withStatus(500)->getBody()->write(json_encode(array("status" => "fail", "data" => array("error" => $e->getMessage()))));
        }
    })->add($authMiddleware);
    
    // Update book
    $app->put('/book/update', function (Request $request, Response $response, array $args) {
        $data = json_decode($request->getBody());
    
        if (!isset($data->old_title) || !isset($data->new_title)) {
            return $response->withStatus(400)->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => "Invalid input data. Both 'old_title' and 'new_title' must be provided."
            )));
        }
    
        $oldTitle = trim($data->old_title);
        $newTitle = trim($data->new_title);
    
        $servername = "localhost";
        $username = "root";
        $password = "";
        $dbname = "library";
    
        try {
            $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
            // Decode token to get userId from JWT token (for token rotation)
            $tokenUserId = $request->getAttribute('decoded')->data->userId;
    
            $oldStmt = $conn->prepare("SELECT COUNT(*) FROM books WHERE title = :old_title");
            $oldStmt->execute([':old_title' => $oldTitle]);
            $oldCount = $oldStmt->fetchColumn();
    
            if ($oldCount == 0) {
                return $response->withStatus(404)->getBody()->write(json_encode(array(
                    "status" => "fail",
                    "data" => "Old book title does not exist."
                )));
            }

            $newStmt = $conn->prepare("SELECT COUNT(*) FROM books WHERE title = :new_title");
            $newStmt->execute([':new_title' => $newTitle]);
            $newCount = $newStmt->fetchColumn();
    
            if ($newCount > 0) {
                return $response->withStatus(409)->getBody()->write(json_encode(array(
                    "status" => "fail",
                    "data" => "New book title already exists."
                )));
            }
    
            $stmt = $conn->prepare("UPDATE books SET title = :new_title WHERE title = :old_title");
            $stmt->execute([
                ':new_title' => $newTitle,
                ':old_title' => $oldTitle
            ]);
    
            // Revoke the current token
            $token = str_replace('Bearer ', '', $request->getHeader('Authorization')[0]);
            $_SESSION['used_tokens'][] = $token;
    
            // Generate a new token
            $key = 'server_hack';
            $iat = time();
            $payload = [
                'iss' => 'http://library.org',
                'aud' => 'http://library.com',
                'iat' => $iat,
                'exp' => $iat + 3600,
                'data' => array("userId" => $tokenUserId)
            ];
            $new_jwt = JWT::encode($payload, $key, 'HS256');
    
            return $response->getBody()->write(json_encode(array(
                "status" => "success",
                "data" => "Book title updated successfully",
                "token" => $new_jwt
            )));
    
        } catch (PDOException $e) {
            return $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("error" => $e->getMessage())
            )));
        }
    })->add($authMiddleware);
    
    // Delete book by title
    $app->delete('/book/delete', function (Request $request, Response $response, array $args) {
        $data = json_decode($request->getBody());
    
        if (!isset($data->title) || empty($data->title)) {
            return $response->getBody()->write(json_encode(array("status" => "fail", "data" => "Invalid input data")));
        }
    
        $title = trim($data->title);
    
        $servername = "localhost";
        $db_username = "root";
        $password = "";
        $dbname = "library";
    
        try {
            $conn = new PDO("mysql:host=$servername;dbname=$dbname", $db_username, $password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
            // Decode token to get userId from JWT token (for token rotation)
            $tokenUserId = $request->getAttribute('decoded')->data->userId;
    
            $stmt = $conn->prepare("SELECT COUNT(*) FROM books WHERE title = :title");
            $stmt->execute([':title' => $title]);
            $count = $stmt->fetchColumn();
    
            if ($count == 0) {
                return $response->getBody()->write(json_encode(array("status" => "fail", "data" => "Book not found")));
            }
    
            $stmt = $conn->prepare("DELETE FROM books WHERE title = :title");
            $stmt->execute([':title' => $title]);
    
            // Revoke the current token
            $token = str_replace('Bearer ', '', $request->getHeader('Authorization')[0]);
            $_SESSION['used_tokens'][] = $token;
    
            // Generate a new token
            $key = 'server_hack';
            $iat = time();
            $payload = [
                'iss' => 'http://library.org',
                'aud' => 'http://library.com',
                'iat' => $iat,
                'exp' => $iat + 3600, 
                'data' => array("userId" => $tokenUserId)
            ];
            $new_jwt = JWT::encode($payload, $key, 'HS256');
 
            return $response->getBody()->write(json_encode(array(
                "status" => "success",
                "data" => "Book deleted successfully",
                "token" => $new_jwt
            )));
    
        } catch (PDOException $e) {
            return $response->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("error" => $e->getMessage())
            )));
        }
    })->add($authMiddleware);
    
    // Display books
    $app->get('/book/display', function (Request $request, Response $response, array $args) {
        $queryParams = $request->getQueryParams();
        $title = $queryParams['title'] ?? null;
    
        $servername = "localhost";
        $db_username = "root";
        $password = "";
        $dbname = "library";
    
        try {
            $conn = new PDO("mysql:host=$servername;dbname=$dbname", $db_username, $password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
            $tokenUserId = $request->getAttribute('decoded')->data->userId;
    
            if ($title) {
                $stmt = $conn->prepare("SELECT books.bookId, books.title, authors.name AS author 
                                        FROM books 
                                        JOIN authors ON books.authorId = authors.authorId 
                                        WHERE books.title = :title");
                $stmt->execute([':title' => $title]);
                $book = $stmt->fetch(PDO::FETCH_ASSOC);
    
                if ($book) {
                    // Revoke the current token
                    $token = str_replace('Bearer ', '', $request->getHeader('Authorization')[0]);
                    $_SESSION['used_tokens'][] = $token;
    
                    // Generate a new token
                    $key = 'server_hack';
                    $iat = time();
                    $payload = [
                        'iss' => 'http://library.org',
                        'aud' => 'http://library.com',
                        'iat' => $iat,
                        'exp' => $iat + 3600, 
                        'data' => array("userId" => $tokenUserId)
                    ];
                    $new_jwt = JWT::encode($payload, $key, 'HS256');
    
                    return $response->getBody()->write(json_encode(array(
                        "status" => "success",
                        "data" => $book,
                        "token" => $new_jwt
                    )));
                } else {
                    return $response->getBody()->write(json_encode(array("status" => "fail", "data" => "Book not found")));
                }
            } else {
                $stmt = $conn->prepare("SELECT books.bookId, books.title, authors.name AS author 
                                        FROM books 
                                        JOIN authors ON books.authorId = authors.authorId");
                $stmt->execute();
                $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
                if (count($books) > 0) {
                    // Revoke the current token
                    $token = str_replace('Bearer ', '', $request->getHeader('Authorization')[0]);
                    $_SESSION['used_tokens'][] = $token;
    
                    // Generate a new token
                    $key = 'server_hack';
                    $iat = time();
                    $payload = [
                        'iss' => 'http://library.org',
                        'aud' => 'http://library.com',
                        'iat' => $iat,
                        'exp' => $iat + 3600, 
                        'data' => array("userId" => $tokenUserId)
                    ];
                    $new_jwt = JWT::encode($payload, $key, 'HS256');
    
                    return $response->getBody()->write(json_encode(array(
                        "status" => "success",
                        "token" => $new_jwt,
                        "data" => $books
                    )));
                } else {
                    return $response->getBody()->write(json_encode(array("status" => "fail", "data" => "No books found")));
                }
            }
    
        } catch (PDOException $e) {
            return $response->getBody()->write(json_encode(array("status" => "fail", "data" => array("title" => $e->getMessage()))));
        }
    })->add($authMiddleware);
    

    // ADd book_author
    $app->post('/books_authors/add', function (Request $request, Response $response, array $args) {
        $data = json_decode($request->getBody());

        if (!isset($data->bookId) || !isset($data->authorId)) {
            return $response->withStatus(400)->getBody()->write(json_encode(array("status" => "fail", "data" => "Invalid input data. Both 'bookId' and 'authorId' must be provided.")));
        }
    
        $bookId = $data->bookId;
        $authorId = $data->authorId;
    
        $servername = "localhost";
        $db_username = "root";
        $password = "";
        $dbname = "library";
    
        try {
            $conn = new PDO("mysql:host=$servername;dbname=$dbname", $db_username, $password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
            // Decode token to get userId from JWT token (for token rotation)
            $tokenUserId = $request->getAttribute('decoded')->data->userId;

            $bookStmt = $conn->prepare("SELECT COUNT(*) FROM books WHERE bookId = :bookId");
            $bookStmt->execute([':bookId' => $bookId]);
            $bookCount = $bookStmt->fetchColumn();
    
            if ($bookCount == 0) {
                return $response->withStatus(404)->getBody()->write(json_encode(array("status" => "fail", "data" => "Book ID not found")));
            }

            $authorStmt = $conn->prepare("SELECT COUNT(*) FROM authors WHERE authorId = :authorId");
            $authorStmt->execute([':authorId' => $authorId]);
            $authorCount = $authorStmt->fetchColumn();
    
            if ($authorCount == 0) {
                return $response->withStatus(404)->getBody()->write(json_encode(array("status" => "fail", "data" => "Author ID not found")));
            }

            $checkStmt = $conn->prepare("SELECT COUNT(*) FROM books_authors WHERE bookId = :bookId AND authorId = :authorId");
            $checkStmt->execute([':bookId' => $bookId, ':authorId' => $authorId]);
            $existingCount = $checkStmt->fetchColumn();
    
            if ($existingCount > 0) {
                return $response->withStatus(409)->getBody()->write(json_encode(array("status" => "fail", "data" => "This book-author combination already exists.")));
            }
    
            $stmt = $conn->prepare("INSERT INTO books_authors (bookId, authorId) VALUES (:bookId, :authorId)");
            $stmt->execute([':bookId' => $bookId, ':authorId' => $authorId]);
    
            // Revoke the current token
            $token = str_replace('Bearer ', '', $request->getHeader('Authorization')[0]);
            $_SESSION['used_tokens'][] = $token;
    
            // Generate a new token
            $key = 'server_hack';
            $iat = time();
            $payload = [
                'iss' => 'http://library.org',
                'aud' => 'http://library.com',
                'iat' => $iat,
                'exp' => $iat + 3600, 
                'data' => array("userId" => $tokenUserId)
            ];
            $new_jwt = JWT::encode($payload, $key, 'HS256');
    
            return $response->withStatus(201)->getBody()->write(json_encode(array(
                "status" => "success",
                "token" => $new_jwt,
                "data" => null
            )));
    
        } catch (PDOException $e) {
            return $response->withStatus(500)->getBody()->write(json_encode(array("status" => "fail", "data" => array("title" => $e->getMessage()))));
        }
    })->add($authMiddleware);
    
    //Update book_author
    $app->put('/books_authors/update', function (Request $request, Response $response, array $args) {
        $data = json_decode($request->getBody());
    
        if (!isset($data->collectionId) || (!isset($data->new_bookId) && !isset($data->new_authorId))) {
            return $response->withStatus(400)->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => "Invalid input data. 'collectionId' must be provided, and at least one of 'new_bookId' or 'new_authorId' must be provided."
            )));
        }
    
        $collectionId = $data->collectionId;
        $new_bookId = $data->new_bookId ?? null;
        $new_authorId = $data->new_authorId ?? null;
    
        $servername = "localhost";
        $db_username = "root";
        $password = "";
        $dbname = "library";
    
        try {
            $conn = new PDO("mysql:host=$servername;dbname=$dbname", $db_username, $password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
            // Decode token to get userId from JWT token (for token rotation)
            $tokenUserId = $request->getAttribute('decoded')->data->userId;
    
            $stmt = $conn->prepare("SELECT * FROM books_authors WHERE collectionId = :collectionId");
            $stmt->execute([':collectionId' => $collectionId]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);
    
            if (!$record) {
                return $response->withStatus(404)->getBody()->write(json_encode(array("status" => "fail", "data" => "Record not found")));
            }
    
            $current_bookId = $record['bookId'];
            $current_authorId = $record['authorId'];

            $updated_bookId = $new_bookId ? $new_bookId : $current_bookId;
            $updated_authorId = $new_authorId ? $new_authorId : $current_authorId;

            $checkStmt = $conn->prepare("SELECT COUNT(*) FROM books_authors WHERE bookId = :bookId AND authorId = :authorId AND collectionId != :collectionId");
            $checkStmt->execute([
                ':bookId' => $updated_bookId,
                ':authorId' => $updated_authorId,
                ':collectionId' => $collectionId
            ]);
            $existingCount = $checkStmt->fetchColumn();
    
            if ($existingCount > 0) {
                return $response->withStatus(409)->getBody()->write(json_encode(array("status" => "fail", "data" => "This book-author combination already exists.")));
            }

            $updateStmt = $conn->prepare("UPDATE books_authors SET bookId = :bookId, authorId = :authorId WHERE collectionId = :collectionId");
            $updateStmt->execute([
                ':bookId' => $updated_bookId,
                ':authorId' => $updated_authorId,
                ':collectionId' => $collectionId
            ]);
    
            // Revoke the current token
            $token = str_replace('Bearer ', '', $request->getHeader('Authorization')[0]);
            $_SESSION['used_tokens'][] = $token;
    
            // Generate a new token
            $key = 'server_hack';
            $iat = time();
            $payload = [
                'iss' => 'http://library.org',
                'aud' => 'http://library.com',
                'iat' => $iat,
                'exp' => $iat + 3600,
                'data' => array("userId" => $tokenUserId)
            ];
            $new_jwt = JWT::encode($payload, $key, 'HS256');
    
            return $response->withStatus(200)->getBody()->write(json_encode(array(
                "status" => "success",
                "token" => $new_jwt,
                "data" => null
            )));
    
        } catch (PDOException $e) {
            return $response->withStatus(500)->getBody()->write(json_encode(array("status" => "fail", "data" => array("title" => $e->getMessage()))));
        }
    })->add($authMiddleware);
    
    //delete book_author
    $app->delete('/books_authors/delete', function (Request $request, Response $response, array $args) {
        $data = json_decode($request->getBody());

        if (!isset($data->collectionId)) {
            return $response->withStatus(400)->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => "Invalid input data. 'collectionId' must be provided."
            )));
        }
    
        $collectionId = $data->collectionId;
    
        $servername = "localhost";
        $db_username = "root";
        $password = "";
        $dbname = "library";
    
        try {
            $conn = new PDO("mysql:host=$servername;dbname=$dbname", $db_username, $password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
            // Decode token to get userId from JWT token (for token rotation)
            $tokenUserId = $request->getAttribute('decoded')->data->userId;
    
            $stmt = $conn->prepare("SELECT COUNT(*) FROM books_authors WHERE collectionId = :collectionId");
            $stmt->execute([':collectionId' => $collectionId]);
            $count = $stmt->fetchColumn();
    
            if ($count == 0) {
                return $response->withStatus(404)->getBody()->write(json_encode(array(
                    "status" => "fail",
                    "data" => "Record not found."
                )));
            }

            $stmt = $conn->prepare("DELETE FROM books_authors WHERE collectionId = :collectionId");
            $stmt->execute([':collectionId' => $collectionId]);
    
            // Revoke the current token
            $token = str_replace('Bearer ', '', $request->getHeader('Authorization')[0]);
            $_SESSION['used_tokens'][] = $token;
    
            // Generate a new token
            $key = 'server_hack';
            $iat = time();
            $payload = [
                'iss' => 'http://library.org',
                'aud' => 'http://library.com',
                'iat' => $iat,
                'exp' => $iat + 3600, 
                'data' => array("userId" => $tokenUserId)
            ];
            $new_jwt = JWT::encode($payload, $key, 'HS256');
    
            return $response->withStatus(200)->getBody()->write(json_encode(array(
                "status" => "success",
                "token" => $new_jwt,
                "data" => null
            )));
    
        } catch (PDOException $e) {
            return $response->withStatus(500)->getBody()->write(json_encode(array(
                "status" => "fail",
                "data" => array("title" => $e->getMessage())
            )));
        }
    })->add($authMiddleware);
    
    //display books_authors
    $app->get('/books_authors/display', function (Request $request, Response $response, array $args) {
        $queryParams = $request->getQueryParams();
        $bookTitle = $queryParams['bookTitle'] ?? null; 
        $authorName = $queryParams['authorName'] ?? null; 
    
        $servername = "localhost";
        $db_username = "root";
        $password = "";
        $dbname = "library";
    
        try {
            $conn = new PDO("mysql:host=$servername;dbname=$dbname", $db_username, $password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
            // Decode token to get userId from JWT token (for token rotation)
            $tokenUserId = $request->getAttribute('decoded')->data->userId;
    
            if ($bookTitle) {
                $stmt = $conn->prepare("SELECT ba.collectionId, b.title AS bookTitle, a.name AS authorName
                                        FROM books_authors ba
                                        JOIN books b ON ba.bookId = b.bookId
                                        JOIN authors a ON ba.authorId = a.authorId
                                        WHERE b.title = :bookTitle");
                $stmt->execute([':bookTitle' => $bookTitle]);
                $relationship = $stmt->fetch(PDO::FETCH_ASSOC);
    
                if ($relationship) {
                    // Revoke the current token
                    $token = str_replace('Bearer ', '', $request->getHeader('Authorization')[0]);
                    $_SESSION['used_tokens'][] = $token;
    
                    // Generate a new token
                    $key = 'server_hack';
                    $iat = time();
                    $payload = [
                        'iss' => 'http://library.org',
                        'aud' => 'http://library.com',
                        'iat' => $iat,
                        'exp' => $iat + 3600, 
                        'data' => array("userId" => $tokenUserId)
                    ];
                    $new_jwt = JWT::encode($payload, $key, 'HS256');
    
                    return $response->withStatus(200)->getBody()->write(json_encode(array(
                        "status" => "success",
                        "data" => $relationship,
                        "token" => $new_jwt
                    )));
                } else {
                    return $response->withStatus(404)->getBody()->write(json_encode(array(
                        "status" => "fail",
                        "data" => "No relationship found for the given book title"
                    )));
                }
            } elseif ($authorName) {
                $stmt = $conn->prepare("SELECT ba.collectionId, b.title AS bookTitle, a.name AS authorName
                                        FROM books_authors ba
                                        JOIN books b ON ba.bookId = b.bookId
                                        JOIN authors a ON ba.authorId = a.authorId
                                        WHERE a.name = :authorName");
                $stmt->execute([':authorName' => $authorName]);
                $relationship = $stmt->fetch(PDO::FETCH_ASSOC);
    
                if ($relationship) {
                    // Revoke the current token
                    $token = str_replace('Bearer ', '', $request->getHeader('Authorization')[0]);
                    $_SESSION['used_tokens'][] = $token;
    
                    // Generate a new token
                    $key = 'server_hack';
                    $iat = time();
                    $payload = [
                        'iss' => 'http://library.org',
                        'aud' => 'http://library.com',
                        'iat' => $iat,
                        'exp' => $iat + 3600, 
                        'data' => array("userId" => $tokenUserId)
                    ];
                    $new_jwt = JWT::encode($payload, $key, 'HS256');
    
                    return $response->withStatus(200)->getBody()->write(json_encode(array(
                        "status" => "success",
                        "data" => $relationship,
                        "token" => $new_jwt
                    )));
                } else {
                    return $response->withStatus(404)->getBody()->write(json_encode(array(
                        "status" => "fail",
                        "data" => "No relationship found for the given author name"
                    )));
                }
            } else {
                $stmt = $conn->prepare("SELECT ba.collectionId, b.title AS bookTitle, a.name AS authorName
                                        FROM books_authors ba
                                        JOIN books b ON ba.bookId = b.bookId
                                        JOIN authors a ON ba.authorId = a.authorId");
                $stmt->execute();
                $relationships = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
                if (count($relationships) > 0) {
                    // Revoke the current token
                    $token = str_replace('Bearer ', '', $request->getHeader('Authorization')[0]);
                    $_SESSION['used_tokens'][] = $token;
    
                    // Generate a new token
                    $key = 'server_hack';
                    $iat = time();
                    $payload = [
                        'iss' => 'http://library.org',
                        'aud' => 'http://library.com',
                        'iat' => $iat,
                        'exp' => $iat + 3600, // Token valid for 1 hour
                        'data' => array("userId" => $tokenUserId)
                    ];
                    $new_jwt = JWT::encode($payload, $key, 'HS256');
    
                    return $response->withStatus(200)->getBody()->write(json_encode(array(
                        "status" => "success",
                        "token" => $new_jwt,
                        "data" => $relationships
                    )));
                } else {
                    return $response->withStatus(404)->getBody()->write(json_encode(array(
                        "status" => "fail",
                        "data" => "No books-authors relationships found"
                    )));
                }
            }
    
        } catch (PDOException $e) {
            return $response->withStatus(500)->getBody()->write(json_encode(array("status" => "fail", "data" => array("title" => $e->getMessage()))));
        }
    })->add($authMiddleware);
    
$app->run();
?>
