<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/auth.php';
check_login();
session_start();
include "config.php";
// DEBUGGING
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// DATABASE
$mysqli = new mysqli(
    $servername,
    $username,
    $password,
    $dbname
);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}
$formResults = "";
// HIDE QUESTION
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'hide'
) {
    $slugToHide = trim($_POST['slug'] ?? '');
    if ($slugToHide !== '') {
        $stmtHide = $mysqli->prepare("
            UPDATE questions
            SET visible = 'h'
            WHERE slug = ?
        ");
        $stmtHide->bind_param("s", $slugToHide);
        $stmtHide->execute();
        $stmtHide->close();
        $formResults = "Question hidden.";
    }
}


// SUBMIT ANSWER
if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && !isset($_POST['action'])
) {

    $slug = trim($_POST["slug"] ?? '');
    $answer = $_POST["answer"] ?? '';
    $tags = trim($_POST["tags"] ?? '');
    if ($slug && $answer) {
        // Clean tags
        $tagArray = array_filter(
            array_map(
                'trim',
                explode(',', $tags)
            )
        );

        $cleanTags = implode(', ', $tagArray);

        $stmt = $mysqli->prepare("
            UPDATE questions
            SET
                answer = ?,
                tags = ?,
                timestamp = NOW(),
                visible = 'y'
            WHERE slug = ?
        ");

        $stmt->bind_param(
            "sss",
            $answer,
            $cleanTags,
            $slug
        );

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $formResults =
                    "Answer submitted successfully!";
            } else {
                $formResults =
                    "No question found with that slug.";
            }

        } else {

            $formResults =
                "Error submitting answer: " .
                $stmt->error;
        }

        $stmt->close();

    } else {

        $formResults =
            "Please provide a slug and an answer.";
    }
}


// FETCH UNANSWERED QUESTIONS
$questionsResult = $mysqli->query("
    SELECT slug, question
    FROM questions
    WHERE visible = 'n'
    ORDER BY id DESC
");
?>

<!DOCTYPE html>

<html>

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=0.5"
    >

    <title>Admin Answer Page</title>

    <meta
        name="description"
        content="askbox admin"
    >

    <link
        href="style.css"
        rel="stylesheet"
        type="text/css"
        media="all"
    >

    <!-- TinyMCE -->
    <script
        src="https://cdn.tiny.cloud/1/9lr4jbj4uwtlwjr2ihwq9rtkp8668s2ctc5jszpmf5xitce1/tinymce/6/tinymce.min.js"
        referrerpolicy="origin"
    ></script>

    <script>
        tinymce.init({
            selector: '#answer',
            menubar: false,
            plugins: 'link lists image',
            toolbar: 'bold italic underline | bullist numlist | link image',
            height: 300,
            paste_data_images: false
        });
    </script>

</head>
<body>

    <!-- Miku gif -->
    <img
        src="https://media.tenor.com/ouQzDmgC9CwAAAAi/miku-vocaloid.gif"
        style="height:400px;"
    >

    <h1>Answer Questions</h1>
    <div class="ask-container">
        <div class="ask-item">
            <?php if ($formResults): ?>
                <div class="message">
                    <?php echo htmlspecialchars($formResults); ?>
                </div>

            <?php endif; ?>

            <!-- ANSWER FORM -->
            <form method="post" action="">

                <div class="form-group">

                    <label for="slug">

                        <strong>Enter Question Slug:</strong>

                    </label>

                    <input
                        type="text"
                        name="slug"
                        id="slug"
                        placeholder="e.g. a1B3"
                        required
                    >

                </div>

                <div class="form-group">

                    <label for="answer">

                        <strong>Your Answer:</strong>

                    </label>

                    <textarea
                        name="answer"
                        id="answer"
                    ></textarea>

                </div>

                <!-- TAGS -->
                <div class="form-group">

                    <label for="tags">

                        <strong>Tags:</strong>

                    </label>

                    <input
                        type="text"
                        name="tags"
                        id="tags"
                        placeholder="games, coding, personal"
                    >

                    <small>
                        Separate tags with commas
                    </small>

                </div>

                <input
                    type="submit"
                    value="Submit Answer"
                    class="cutiepie"
                >

            </form>
        </div>
        <br>

        <div class="unanswered">

            <h2>Unanswered Questions</h2>

        </div>

        <br>

        <?php
        if ($questionsResult->num_rows > 0) {

            while ($row = $questionsResult->fetch_assoc()) {

                $slug = htmlspecialchars(
                    (string)$row['slug']
                );

                $question = htmlspecialchars(
                    (string)(
                        $row['question']
                        ?? 'No question'
                    )
                );

                echo "
                <div class='unansweredask-item'>

                    <form
                        method='post'
                        style='display:inline'
                    >

                        <input
                            type='hidden'
                            name='action'
                            value='hide'
                        >

                        <input
                            type='hidden'
                            name='slug'
                            value='$slug'
                        >
                        <input
                            type='submit'
                            value='Hide'
                        >
                    </form>
                    <strong>$slug</strong>
                    - $question
                </div>
                ";
            }
        } else {
            echo "<p>No unanswered questions.</p>";
        }
        ?>
    </div>
</body>
</html>

