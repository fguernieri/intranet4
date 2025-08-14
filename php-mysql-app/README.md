# README.md

# PHP MySQL Application

## Overview
This project is a PHP application that interacts with a MySQL database. It follows the MVC (Model-View-Controller) architecture, separating the application logic into distinct components.

## Project Structure
```
php-mysql-app
├── src
│   ├── config
│   │   └── database.php
│   ├── models
│   │   └── index.php
│   ├── controllers
│   │   └── index.php
│   └── views
│       └── index.php
├── public
│   └── index.php
├── tests
│   └── index.php
├── composer.json
└── README.md
```

## Requirements
- PHP 7.4 or higher
- Composer
- MySQL database

## Setup Instructions
1. Clone the repository:
   ```
   git clone <repository-url>
   ```
2. Navigate to the project directory:
   ```
   cd php-mysql-app
   ```
3. Install dependencies using Composer:
   ```
   composer install
   ```
4. Configure the database connection in `src/config/database.php` with your database credentials.
5. Create the necessary database and tables as defined in your models.
6. Start the application by accessing `public/index.php` in your web browser.

## Usage
- The application follows the MVC pattern. 
- Models are defined in `src/models/index.php`.
- Controllers handle the logic in `src/controllers/index.php`.
- Views are rendered in `src/views/index.php`.

## Testing
- Unit tests can be found in the `tests/index.php` file. Run tests using your preferred testing framework.

## License
This project is licensed under the MIT License.