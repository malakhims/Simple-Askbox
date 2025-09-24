<?php
require_once 'auth.php';
check_login(); // This will show login form if not logged in

session_start();

// Database credentials
include "config.php";

// Enable errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Connect to database
$mysqli = new mysqli($servername, $username, $password, $dbname);
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$formResults = "";

// Handle answer submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $slug = trim($_POST["slug"]);
    $answer = $_POST["answer"]; // Allow HTML

    if ($slug && $answer) {
        $stmt = $mysqli->prepare("UPDATE questions SET answer = ?, timestamp = NOW(), visible = 'y' WHERE slug = ?");
        $stmt->bind_param("ss", $answer, $slug);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $formResults = "Answer submitted successfully!";
            } else {
                $formResults = "No question found with that slug.";
            }
        } else {
            $formResults = "Error submitting answer: " . $stmt->error;
        }

        $stmt->close();
    } else {
        $formResults = "Please provide a slug and an answer.";
    }
}

// Fetch unanswered questions (visible = 'n')
$questionsResult = $mysqli->query("SELECT slug, question FROM questions WHERE visible = 'n' ORDER BY id DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Admin Answer Page</title>
    <!-- TinyMCE WYSIWYG -->
       <script src="https://cdn.tiny.cloud/1/9lr4jbj4uwtlwjr2ihwq9rtkp8668s2ctc5jszpmf5xitce1/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
        tinymce.init({
            selector: '#answer',
            menubar: false,
            plugins: 'link lists',
            toolbar: 'bold italic underline | bullist numlist | link',
            height: 300
        });
    </script>

        <!DOCTYPE html>
        <html>
        <head>
        <meta charset="UTF-8">  
        <meta name="viewport" content="width=device-width, initial-scale=0.5">
        <title>Mala's Askbox</title>
        <link rel="icon" href="https://mal.ophanimkei.com/images/halo.ico">
        <meta name="description" content="ask me a question!">
        <link href="style.css" rel="stylesheet" type="text/css" media="all"> 
        </head>
        <body>

            <img src="https://ophanimkei.com/images/ellie.jpg" title="by io" style="height: 400px;">

            <h1>Answer Questions</h1>

            <div class="ask-container">

            <div class="ask-item">
                <?php if ($formResults): ?>
                    <div class="message"><?php echo $formResults; ?></div>
                <?php endif; ?>

                <form method="post" action="">
                    <div class="form-group">
                        <label for="slug"><strong>Enter Question Slug:</strong></label>
                        <input type="text" name="slug" id="slug" placeholder="e.g. a1B3" required>
                    </div>

                    <div class="form-group">
                        <label for="answer"><strong>Your Answer:</strong></label>
                        <textarea name="answer" id="answer"></textarea>
                    </div>

                    <input type="submit" value="Submit Answer" class="cutiepie">
                    </form>

                    <br>
                
                </div>

                <br>

                <div class="unanswered">
                    <h2>Unanswered Questions</h2>
                </div>

                <br>


                    <?php
                    if ($questionsResult->num_rows > 0) {
                        while ($row = $questionsResult->fetch_assoc()) {
                            $slug = htmlspecialchars((string)$row['slug']);
                            $question = htmlspecialchars((string)($row['question'] ?? 'No question'));
                            echo "<div class='ask-item'><strong>$slug</strong> - $question</div>";
                        }
                    } else {
                        echo "<li>No unanswered questions</li>";
                    }
                    ?>
            </div>
    </body>
    </html>
