# Library API Documentation

## Description
This system is a web API application built with the Slim Framework and uses JWT (JSON Web Token) for authentication and authorization. This system allows users to manage a library database with the following functionality:

- User registration, authentication, and profile management.
- Author management: add, update, display, and delete authors.
- Book management: add, update, display, and delete books.
- Manage relationships between books and authors.

## Tools and Software Used 

- **PHP**: A robust server-side scripting language for developing dynamic and secure APIs.
- **Slim Framework**: A lightweight PHP framework designed for building RESTful web services with efficiency.
- **JWT (JSON Web Token)**: A standard for secure, stateless authentication and authorization in the API.
- **MySQL**: A powerful relational database system used to manage and store information about users, authors, books, and their relationships.
- **JSON**: A lightweight data format used for seamless communication between the client and the server in API requests and responses.

## Features

### Authentication and Security
- JWT is used for generating and validating access tokens.
- Middleware ensures that endpoints are protected and checks if tokens are reused.

### Core Functionalities
- **User Management**: Allows users to register, authenticate, update their accounts, and view/delete users.
- **Author Management**: Enables managing author information with secure operations.
- **Book Management**: Handles the addition, update, and deletion of books.
- **Books-Authors Relationships**: Maintains many-to-many relationships between books and authors.


### Token Management
- Tokens have an expiration of 1 hour.
- Used tokens are invalidated using `$_SESSION['used_tokens']`.

## Endpoints 

1. ### Register a User
    - **Endpoint:** `/user/register`  
    - **Method:** `POST`  
    - **Description:** 
        This API endpoint allows new users to register by providing a username and password. The system validates the input, ensures the username is unique, and securely stores the user's credentials in the database using hashed passwords.
    - **Sample Request(JSON):**
        ```json
            {
            "username":"example_username",
            "password":"example_password"
            }
        ```
    - **Response:**
        - **On Success**
            ```json
                {
                "status": "success",
                "data": null
                }
            ```
        - **On Failure (Username already exists):**
            ```json
                {
                "status": "fail",
                "data": "Username already exists"
                }
            ```
---
2. ### Authenticate a User
    - **Endpoint:** `/user/authenticate`  
    - **Method:** `POST`  
    - **Description:**  
      This API endpoint verifies a user's credentials by checking their username and password against the database. If the authentication is successful, a JSON Web Token (JWT) is generated and returned to the user.  
    
    - **Sample Request (JSON):**
        ```json
        {
            "username": "example_username",
            "password": "example_password"
        }
        ```
    - **Response:**
        - **On Success:**
            ```json
            {
                "status": "success",
                "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
                "data": null
            }
            ```
        - **On Failure (Missing or invalid input):**
            ```json
            {
                "status": "fail",
                "data": {
                    "title": "Invalid input data"
                }
            }
            ```
        - **On Failure (Incorrect username):**
            ```json
            {
                "status": "fail",
                "data": {
                    "title": "Incorrect username"
                }
            }
            ```
        - **On Failure (Incorrect password):**
            ```json
            {
                "status": "fail",
                "data": {
                    "title": "Incorrect password"
                }
            }
            ```
---
3. ### Update User Information
    - **Endpoint:** `/user/update`  
    - **Method:** `PUT`  
    - **Description:**  
        This API endpoint allows users to update their account information by providing their current username, along with a new username and a new password. The endpoint requires an **Authorization** header with a valid JSON Web Token (JWT). Upon successful update, a new JWT token is generated.
    
    
    - **Sample Request (JSON):**
        ```json
        {
            "username": "current_username",
            "new_username": "new_username",
            "new_password": "new_password"
        }
        ```
    - **Response:**
        - **On Success:**
            ```json
            {
                "status": "success",
                "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
                "data": null
            }
            ```
        - **On Failure (Invalid input data):**
            ```json
            {
                "status": "fail",
                "data": "Invalid input data"
            }
            ```
        - **On Failure (Invalid username):**
            ```json
            {
                "status": "fail",
                "data": "Invalid username"
            }
            ```
---
4. ### Delete a User
    - **Endpoint:** `/user/delete`  
    - **Method:** `DELETE`  
    - **Description:**  
      This API endpoint allows users to delete their account by providing their username. It first verifies if the username exists in the database and matches the logged-in user's account, then proceeds to delete the user account. Upon successful deletion, a new JWT token is generated to invalidate the old token.
    
    - **Sample Request (JSON):**
        ```json
        {
            "username": "example_username"
        }
        ```
    
    - **Response:**
        - **On Success:**
            ```json
            {
                "status": "success",
                "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
                "data": "User account deleted"
            }
            ```
        - **On Failure (Invalid input data):**
            ```json
            {
                "status": "fail",
                "data": "Invalid input data"
            }
            ```
        - **On Failure (Invalid username):**
            ```json
            {
                "status": "fail",
                "data": "Invalid username"
            }
            ```
---
5. ### Display Users
    - **Endpoint:** `/user/display`  
    - **Method:** `GET`  
    - **Description:**  
      This API endpoint allows users to retrieve their account information or display all users. This endpoint requires an **Authorization** header with a valid JSON Web Token (JWT). A new token is generated and returned with the response.
    
    - **Sample Request:**
        ```json
    
        ```
    - **Response:**
        - **On Success:**
            ```json
            {
                "status": "success",
                "data": [
                    {
                        "userId": 1,
                        "username": "username 1"
                    },
                    {
                        "userId": 2,
                        "username": "username 2"
                    }
                ],
                "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
            }
            ```
        - **On Failure:**
            ```json
            {
                "status": "fail",
                "data": "No users found"
            }
            ```
---
6. ### Add an Author
    - **Endpoint:** `/author/add`  
    - **Method:** `POST`  
    - **Description:**  
      This API endpoint allows users to add a new author by providing the author's name. It first checks if the name is valid and not empty. The system also verifies that the author's name does not already exist in the database before inserting a new author. After successfully adding the author, a new JWT token is generated to invalidate the old token.
    
    - **Sample Request (JSON):**
        ```json
        {
            "name": "Author Name"
        }
        ```
    
    - **Response:**
        - **On Success:**
            ```json
            {
                "status": "success",
                "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
                "data": null
            }
            ```
        - **On Failure (Missing name/empty):**
            ```json
            {
                "status": "fail",
                "data": "Invalid input data"
            }
            ```
        - **On Failure (Author's name already exists):**
            ```json
            {
                "status": "fail",
                "data": "Author's name already exists"
            }
            ```
---
7. ### Update an Author's Name
    - **Endpoint:** `/author/update`  
    - **Method:** `PUT`  
    - **Description:**  
      This API endpoint allows users to update an author's name by providing both the old name and the new name. The system checks if the old author's name exists and ensures the new name doesn't already exist in the database. If valid, the author's name is updated. After the update, the current JWT token is invalidated, and a new JWT token is generated for the user.
    
    - **Sample Request (JSON):**
        ```json
        {
            "old_name": "Old Author Name",
            "new_name": "New Author Name"
        }
        ```
    
    - **Response:**
        - **On Success:**
            ```json
            {
                "status": "success",
                "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
                "data": null
            }
            ```
        - **On Failure (Invalid input data):**
            ```json
            {
                "status": "fail",
                "data": "Both 'old_name' and 'new_name' must be provided"
            }
            ```
        - **On Failure (Old author's name does not exist):**
            ```json
            {
                "status": "fail",
                "data": "Old author's name does not exist"
            }
            ```
        - **On Failure (New author's name already exists):**
            ```json
            {
                "status": "fail",
                "data": "Author's new name already exists"
            }
            ```
---
8. ### Delete an Author
    - **Endpoint:** `/author/delete`  
    - **Method:** `DELETE`  
    - **Description:**  
      This API endpoint allows users to delete an author from the database by providing the author's name. It checks if the author exists, and if so, removes the author from the system. The current JWT token is invalidated, and a new JWT token is generated for the user after the deletion.
    
    - **Sample Request (JSON):**
        ```json
        {
            "name": "Author Name"
        }
        ```
    
    - **Response:**
        - **On Success:**
            ```json
            {
                "status": "success",
                "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
                "data": null
            }
            ```
        - **On Failure (Author not found):**
            ```json
            {
                "status": "fail",
                "data": "Author not found"
            }
            ```
---
9. ### Display Authors
    - **Endpoint:** `/author/display`  
    - **Method:** `GET`  
    - **Description:**  
      This API endpoint retrieves a list of all authors in the system. It fetches the details of every author stored in the database. The current JWT token is invalidated, and a new JWT token is generated for the user after displaying.
    
    - **Sample Request:**
        ```json
        
        ```
    
    - **Response:**
        - **On Success:**
            ```json
            {
                "status": "success",
                "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
                "data": [
                    {
                        "authorId": 1,
                        "name": "Author Name 1"
                    },
                    {
                        "authorId": 2,
                        "name": "Author Name 2"
                    }
                ]
            }
            ```
        - **On Failure (No authors found):**
            ```json
            {
                "status": "fail",
                "data": "No authors found"
            }
            ```
---
10. ### Add a Book
    - **Endpoint:** `/book/add`  
    - **Method:** `POST`  
    - **Description:**  
      This API endpoint allows users to add a new book by providing the book's title and the corresponding author ID. The system validates that the author ID exists in the database, checks for duplicates by the book title, and then inserts the new book into the database. If the book is successfully added, a new JWT token is returned and the old token will be revoked.
    
    - **Sample Request (JSON):**
        ```json
        {
            "title": "Book Title",
            "authorId": 1
        }
        ```
    
    - **Response:**
        - **On Success:**
            ```json
            {
                "status": "success",
                "data": "Book added successfully",
                "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
            }
            ```
    
        - **On Failure (Invalid Input Data):**
            ```json
            {
                "status": "fail",
                "data": "Invalid input data. Both 'title' and 'authorId' must be provided."
            }
            ```
    
        - **On Failure (Author ID Not Found):**
            ```json
            {
                "status": "fail",
                "data": "Author ID not found"
            }
            ```
    
        - **On Failure (Book Already Exists):**
            ```json
            {
                "status": "fail",
                "data": "Book with this title already exists"
            }
            ```
---
11. ### Update a Book's Title
    - **Endpoint:** `/book/update`
    - **Method:** `PUT`
    - **Description:**  
      This API endpoint allows a user to update the title of an existing book. The user needs to provide the current title (`old_title`) and the new title (`new_title`). The system checks whether the old title exists, ensures the new title does not already exist, and then updates the book title in the database. A new JWT token is generated and returned after a successful update.
    
    - **Sample Request (JSON):**
        ```json
        {
            "old_title": "Old Book Title",
            "new_title": "Updated Book Title"
        }
        ```
    
    - **Response:**
        - **On Success:**
            ```json
            {
                "status": "success",
                "data": "Book title updated successfully",
                "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
            }
            ```
    
        - **On Failure (Invalid Input Data):**
            ```json
            {
                "status": "fail",
                "data": "Invalid input data. Both 'old_title' and 'new_title' must be provided."
            }
            ```
    
        - **On Failure (Old Title Not Found):**
            ```json
            {
                "status": "fail",
                "data": "Old book title does not exist."
            }
            ```
    
        - **On Failure (New Title Already Exists):**
            ```json
            {
                "status": "fail",
                "data": "New book title already exists."
            }
            ```
---
12. ### Delete a Book 
    - **Endpoint:** `/book/delete`
    - **Method:** `DELETE`
    - **Description:**  
      This API endpoint allows a user to delete a book by its title. The user must provide the title of the book they want to delete. The system checks if the book exists, deletes it from the database if it does, and generates a new JWT token after the operation.
    
    - **Sample Request (JSON):**
        ```json
        {
            "title": "Book Title"
        }
        ```
    
    - **Response:**
        - **On Success:**
            ```json
            {
                "status": "success",
                "data": "Book deleted successfully",
                "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
            }
            ```
    
        - **On Failure (Invalid Input Data):**
            ```json
            {
                "status": "fail",
                "data": "Invalid input data"
            }
            ```
    
        - **On Failure (Book Not Found):**
            ```json
            {
                "status": "fail",
                "data": "Book not found"
            }
            ```
---
13. ### Display All Books
    - **Endpoint:** `/book/display`
    - **Method:** `GET`
    - **Description:**  
      This API endpoint allows a user to retrieve all books in the library. the system will return a list of all books available in the database, along with their titles and authors, and generates a new JWT token after the operation.
    
    - **Sample Request:**
        ```json
    
        ```
    
    - **Response:**
        - **On Success:**
            ```json
            {
                "status": "success",
                "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
                "data": [
                    {
                        "bookId": 1,
                        "title": "Book Title 1",
                        "author": "Author Name 1"
                    },
                    {
                        "bookId": 2,
                        "title": "Book Title 2",
                        "author": "Author Name 2"
                    }
                ]
            }
            ```
    
        - **On Failure (No Books Found):**
            ```json
            {
                "status": "fail",
                "data": "No books found"
            }
            ```
---
14. ### Add Book-Author Relationship
    - **Endpoint:** `/books_authors/add`
    - **Method:** `POST`
    - **Description:**  
      This API endpoint allows a user to associate a book with an author. Both `bookId` and `authorId` must be provided in the request body. The system will check if the provided IDs exist in their respective tables (`books` and `authors`) and ensure that the combination does not already exist. If the relationship is valid and unique, it will be added to the database and a new token will be issued.
    
    - **Sample Request (JSON):**
        ```json
        {
            "bookId": 1,
            "authorId": 2
        }
        ```
    
    - **Response:**
        - **On Success (Relationship Added):**
            ```json
            {
                "status": "success",
                "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
                "data": null
            }
            ```
        - **On Failure (Invalid Input Data):**
            ```json
            {
                "status": "fail",
                "data": "Invalid input data. Both 'bookId' and 'authorId' must be provided."
            }
            ```
    
        - **On Failure (Book ID Not Found):**
            ```json
            {
                "status": "fail",
                "data": "Book ID not found"
            }
            ```
    
        - **On Failure (Author ID Not Found):**
            ```json
            {
                "status": "fail",
                "data": "Author ID not found"
            }
            ```
    
        - **On Failure (Duplicate Entry):**
            ```json
            {
                "status": "fail",
                "data": "This book-author combination already exists."
            }
            ```
---
15. ### Update Book-Author Relationship
    - **Endpoint:** `/books_authors/update`
    - **Method:** `PUT`
    - **Description:**  
      This API endpoint allows a user to update an existing book-author relationship. The `collectionId` must be provided to identify the record to update. At least one of `new_bookId` or `new_authorId` must be included in the request body. The system ensures the updated combination does not conflict with existing records before making the changes. Upon successful update, the system will revoke the current token and return a new token for further authentication.
    
    - **Sample Request (JSON):**
        ```json
        {
            "collectionId": 1,
            "new_bookId": 2,
            "new_authorId": 3
        }
        ```
    
    - **Response:**
        - **On Success:**
            ```json
            {
                "status": "success",
                "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
                "data": null
            }
            ```
        - **On Failure (Invalid Input Data):**
            ```json
            {
                "status": "fail",
                "data": "Invalid input data. 'collectionId' must be provided, and at least one of 'new_bookId' or 'new_authorId' must be provided."
            }
            ```
    
        - **On Failure (Record Not Found):**
            ```json
            {
                "status": "fail",
                "data": "Record not found"
            }
            ```
    
        - **On Failure (Duplicate Entry):**
            ```json
            {
                "status": "fail",
                "data": "This book-author combination already exists."
            }
            ```
---
16. ### Delete Book-Author Relationship
    - **Endpoint:** `/books_authors/delete`
    - **Method:** `DELETE`
    - **Description:**  
      This API endpoint allows a user to delete an existing book-author relationship from the database. The `collectionId` must be provided in the request body to identify the record to delete.  Upon successful deletion, the system will revoke the current token and return a new token for further authentication.
    
    - **Sample Request:**
        ```json
        {
            "collectionId": 1
        }
        ```
    
    - **Response:**
        - **On Success (Relationship Deleted):**
            ```json
            {
                "status": "success",
                "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
                "data": null
            }
            ```
    
        - **On Failure (Invalid Input Data):**
            ```json
            {
                "status": "fail",
                "data": "Invalid input data. 'collectionId' must be provided."
            }
            ```
    
        - **On Failure (ecord Not Found):**
            ```json
            {
                "status": "fail",
                "data": "Record not found."
            }
            ```
---
17. ### Display Books-Authors Relationships
    - **Endpoint:** `/books_authors/display`
    - **Method:** `GET`
    - **Description:**  
      This API endpoint is used to display all book-author relationships in the database. Upon successful retrieval of the data, the system will revoke the current token and return a new token for further authentication.
    
    
    - **Sample Request:**
        ```json
    
        ```
    
    - **Response:**
        - **On Success:**
            ```json
            {
                "status": "success",
                "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
                "data": [
                    {
                        "collectionId": 1,
                        "bookTitle": "The Great Gatsby",
                        "authorName": "F. Scott Fitzgerald"
                    },
                    {
                        "collectionId": 2,
                        "bookTitle": "The Catcher in the Rye",
                        "authorName": "J.D. Salinger"
                    }
                ]
            }
            ```
    
        - **On Failure (No Relationships Found):**
            ```json
            {
                "status": "fail",
                "data": "No books-authors relationships found"
            }
            ```

