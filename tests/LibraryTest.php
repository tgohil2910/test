<?php
use PHPUnit\Framework\TestCase;
use MongoDB\BSON\ObjectId;

class LibraryTest extends TestCase
{
    private $app;

    protected function setUp(): void
    {
        // Initialize the app
        $this->app = new LibraryApp();
        // Clean the database before every test to ensure a "Fresh Start"
        $this->app->getCollection()->drop();
    }

    /**
     * Test 1: Check if Bootstrapper seeds data correctly
     */
    public function testBootstrapperSeedsData()
    {
        $this->app->bootstrapper();
        $count = $this->app->getCollection()->countDocuments();
        
        $this->assertGreaterThan(0, $count, "The database should contain books after bootstrapping.");
    }

    /**
     * Test 2: Check if Borrowing a book updates status and history
     */
    public function testBorrowBookUpdatesDatabase()
    {
        // 1. Insert a dummy book manually to test against
        $dummyBook = [
            'title' => 'Unit Test Book',
            'author' => 'Test Author',
            'category' => 'Engineering',
            'status' => 'available',
            'borrowHistory' => []
        ];
        $insertResult = $this->app->getCollection()->insertOne($dummyBook);
        $id = $insertResult->getInsertedId(); // This is an ObjectId

        // 2. Execute the borrow logic
        // Note: Pass the ID and a Name directly to skip the STDIN prompt
        $this->app->borrowBook($id, "AutoGrader Bot");

        // 3. Fetch the book back from Mongo to verify changes
        $updatedBook = $this->app->getCollection()->findOne(['_id' => $id]);

        $this->assertEquals('borrowed', $updatedBook['status'], "Book status should be 'borrowed'.");
        $this->assertCount(1, $updatedBook['borrowHistory'], "Borrow history should have 1 entry.");
        $this->assertEquals('AutoGrader Bot', $updatedBook['borrowHistory'][0]['borrower']);
    }

    /**
     * Test 3: Check if filtering by category works
     */
    public function testEngineeringFilter()
    {
        // Insert one Engineering and one Fiction book
        $this->app->getCollection()->insertMany([
            ['title' => 'Eng Book', 'category' => 'Engineering'],
            ['title' => 'Fic Book', 'category' => 'Fiction']
        ]);

        // Capture the output of the function
        ob_start();
        $this->app->showEngineeringBooks();
        $output = ob_get_clean();

        $this->assertStringContainsString('Eng Book', $output);
        $this->assertStringNotContainsString('Fic Book', $output);
    }
}