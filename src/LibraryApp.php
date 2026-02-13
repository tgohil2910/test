<?php

require __DIR__ . '/../vendor/autoload.php';

use MongoDB\Client;
use MongoDB\BSON\ObjectId;

class LibraryApp
{
    private $collection;

    public function __construct()
    {
        // Connection string for local dev and GitHub Actions service
        $client = new Client("mongodb://localhost:27017");
        $this->collection = $client->libraryDB->books;
    }

    public function getCollection()
    {
        return $this->collection;
    }

    public function menu()
    {
        while (true) {
            echo "\n--- E-Library Menu ---\n";
            echo "1. Bootstrapper (Seed Data)\n";
            echo "2. Borrow Book (Update with Log)\n";
            echo "3. Show Engineering Books\n";
            echo "4. Exit\n";
            echo "Select an option: ";

            $choice = trim(fgets(STDIN));

            switch ($choice) {
                case 1:
                    $this->bootstrapper();
                    break;
                case 2:
                    echo "Enter Book ID: ";
                    $id = trim(fgets(STDIN));
                    $this->borrowBook($id);
                    break;
                case 3:
                    $this->showEngineeringBooks();
                    break;
                case 4:
                    exit("Goodbye!\n");
                default:
                    echo "Invalid option.\n";
            }
        }
    }

    /**
     * Task 1: Seed data from JSON
     */
    public function bootstrapper()
    {
        $count = $this->collection->countDocuments();
        if ($count > 0) {
            echo "Database already seeded ($count books found). Skipping.\n";
            return;
        }

        // Looks for seed_data.json in the same folder as this script
        $jsonPath = __DIR__ . '/seed_data.json';
        if (!file_exists($jsonPath)) {
            echo "Error: seed_data.json not found at $jsonPath\n";
            return;
        }

        $data = json_decode(file_get_contents($jsonPath), true);
        if (empty($data)) {
            echo "Error: seed_data.json is empty or invalid.\n";
            return;
        }

        $result = $this->collection->insertMany($data);
        echo "Seeded " . $result->getInsertedCount() . " books into the database.\n";
    }

    /**
     * Task 2: Update status and push to history
     * @param mixed $bookId String or ObjectId
     * @param string|null $borrower Provided by tests, or null for manual input
     */
    public function borrowBook($bookId, $borrower = null)
    {
        // Convert string ID from menu to MongoDB ObjectId
        if (!($bookId instanceof ObjectId)) {
            try {
                $bookId = new ObjectId($bookId);
            } catch (Exception $e) {
                echo "Invalid ID format: $bookId\n";
                return;
            }
        }

        $book = $this->collection->findOne(['_id' => $bookId]);
        if (!$book) {
            echo "Book with ID '$bookId' not found.\n";
            return;
        }

        if ($book['status'] === 'borrowed') {
            echo "Book '{$book['title']}' is already borrowed.\n";
            return;
        }

        // If no borrower name is passed (manual use), ask for it
        if ($borrower === null) {
            echo "Enter Borrower Name: ";
            $borrower = trim(fgets(STDIN));
        }

        $result = $this->collection->updateOne(
            ['_id' => $bookId],
            [
                '$set' => ['status' => 'borrowed'],
                '$push' => ['borrowHistory' => [
                    'borrower' => $borrower,
                    'borrowDate' => date('Y-m-d H:i:s'),
                ]]
            ]
        );

        if ($result->getModifiedCount() === 1) {
            echo "Book '{$book['title']}' successfully borrowed by $borrower.\n";
        } else {
            echo "Failed to update the book.\n";
        }
    }

    /**
     * Task 3: Filter documents
     */
    public function showEngineeringBooks()
    {
        $cursor = $this->collection->find(['category' => 'Engineering']);
        $found = false;

        echo "\n--- Engineering Books ---\n";
        foreach ($cursor as $book) {
            $found = true;
            echo "ID: {$book['_id']} | Title: {$book['title']} | Author: {$book['author']} | Status: {$book['status']}\n";
        }

        if (!$found) {
            echo "No engineering books found.\n";
        }
    }
}

// Logic to prevent the menu from running during PHPUnit tests
if (php_sapi_name() !== 'cli' || !debug_backtrace()) {
    if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
        $app = new LibraryApp();
        $app->menu();
    }
}