<?php
session_start();
include "config.php";
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


// FORM SUBMISSION
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Honeypot anti-spam
    if (!empty($_POST['website2'])) {
        die("Spam detected");
    }
    $question = trim($_POST["question"]);
    // Prevent empty submissions
    if ($question === '') {
        $formresults = "Please enter a question.";
    } else {
        // Check duplicates
        $check_query = "SELECT id FROM questions WHERE question = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("s", $question);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        if ($check_result->num_rows > 0) {
            $formresults = "This question has already been submitted!";

        } else {
            // Generate unique 4-character slug
            $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            do {
                $slug = '';
                for ($i = 0; $i < 4; $i++) {
                    $slug .= $chars[rand(0, strlen($chars) - 1)];
                }
                $slug_check_stmt = $conn->prepare(
                    "SELECT id FROM questions WHERE slug = ?"
                );
                $slug_check_stmt->bind_param("s", $slug);
                $slug_check_stmt->execute();
                $slug_check_result = $slug_check_stmt->get_result();
            } while ($slug_check_result->num_rows > 0);

            $slug_check_stmt->close();

            // Insert question
            $sql = "
                INSERT INTO questions
                (question, slug, visible, timestamp)
                VALUES (?, ?, 'n', NOW())
            ";

            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("ss", $question, $slug);
                if ($stmt->execute()) {
                    $formresults = "Your question has been submitted!";
                    // Discord webhook
                    $webhook_url = "https://discord.com/api/webhooks/blahblahblah";
                    $message =
                        "New Question Submitted:\n" .
                        "Question: $question\n" .
                        "Slug: $slug";
                    $data = [
                        "content" => $message
                    ];
                    $curl = curl_init($webhook_url);
                    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt(
                        $curl,
                        CURLOPT_POSTFIELDS,
                        json_encode($data)
                    );
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt(
                        $curl,
                        CURLOPT_HTTPHEADER,
                        [
                            'Content-Type: application/json'
                        ]
                    );
                    curl_exec($curl);
                    curl_close($curl);
                } else {
                    $formresults =
                        "Your form was not submitted. Maybe try again? " .
                        $stmt->error;
                }
                $stmt->close();
            } else {
                $formresults =
                    "Form submission failed: " .
                    $conn->error;
            }
        }
        $check_stmt->close();
    }
}


// TAG CLOUD
$tag_cloud = [];

$tag_query = $conn->query("
    SELECT tags
    FROM questions
    WHERE tags IS NOT NULL
    AND tags != ''
");

if ($tag_query && $tag_query->num_rows > 0) {
    while ($tag_row = $tag_query->fetch_assoc()) {
        $tags = array_map(
            'trim',
            explode(',', $tag_row['tags'])
        );

        foreach ($tags as $tag) {
            if ($tag !== '') {
                $tag_cloud[$tag] = isset($tag_cloud[$tag])
                    ? $tag_cloud[$tag] + 1
                    : 1;
            }
        }
    }
}


// TAG FILTERING
$selected_tag = isset($_GET['tag'])
    ? trim($_GET['tag'])
    : '';

if ($selected_tag !== '') {
    $sql = "
        SELECT *
        FROM questions
        WHERE visible = 'y'
        AND FIND_IN_SET(
            ?,
            REPLACE(
                REPLACE(
                    REPLACE(tags, ', ', ','),
                ' ,', ','),
            ',,', ',')
        )
        ORDER BY timestamp DESC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $selected_tag);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query("
        SELECT *
        FROM questions
        WHERE visible = 'y'
        ORDER BY timestamp DESC
    ");
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=0.5">
    <title>Askbox</title>
    <link rel="icon" href="#">
    <meta name="description" content="askbox">
    <link href="style.css" rel="stylesheet" type="text/css" media="all">
</head>
<body>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <div class="form-group">

            <!-- Miku gif -->
            <img
                src="https://media.tenor.com/ouQzDmgC9CwAAAAi/miku-vocaloid.gif"
                style="height:400px;"
            >

            <br>
            <label>
                <b>Ask and Submissions</b>
            </label>
            <br>

            <input
                style="width:50%;height:70px;border-radius:5px;"
                type="text"
                name="question"
                class="form-control"
                required
            >
        </div>

        <!-- Honeypot -->
        <div style="display:none;">
            <input
                type="text"
                name="website2"
                autocomplete="off"
            >
        </div>

        <div class="form-group">
            <input
                type="submit"
                class="cutiepie"
                value="Submit"
            >
        </div>
    </form>

    <div class="message">
        <?php echo isset($formresults) ? $formresults : ''; ?>
    </div>

    <h2>Questions Answered Thus Far</h2>

    <!-- TAG CLOUD -->
    <?php if (!empty($tag_cloud)) : ?>
        <div class="tag-cloud" style="margin-bottom:20px;">
            <?php foreach ($tag_cloud as $tag => $count) : ?>

                <a
                    href="?tag=<?php echo urlencode($tag); ?>"
                    style="
                        font-size: <?php echo 12 + ($count * 2); ?>px;
                        margin:5px;
                        text-decoration:none;
                    "
                >
                    <?php echo htmlspecialchars($tag); ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- ACTIVE TAG -->
    <?php if ($selected_tag !== '') : ?>
        <div class="tag-filter-msg" style="margin-bottom:20px;">
            Viewing tag:
            <strong>
                <?php echo htmlspecialchars($selected_tag); ?>
            </strong>
            <a href="?">[clear filter]</a>

        </div>

    <?php endif; ?>

    <?php
    if ($result->num_rows > 0) {
        echo "<div class='ask-container'>";
        while ($row = $result->fetch_assoc()) {
            $question_id = $row["id"];
            echo "<div class='ask-item' id='question-$question_id'>";

            // QUESTION
            echo "<div class='question'>";
            echo "<strong>Q:</strong> ";
            echo htmlspecialchars($row["question"]);
            echo "</div>";

            // ANSWER
            echo "<div class='answer'>";
            echo "<strong>A:</strong> ";
            echo $row["answer"];
            echo "</div>";

            // TAGS
            if (!empty($row["tags"])) {
                $tags = array_map(
                    'trim',
                    explode(',', $row["tags"])
                );

                echo "<div class='tags'>";
                echo "<strong>Tags:</strong> ";
                foreach ($tags as $tag) {
                    $safe_tag = htmlspecialchars($tag);
                    echo "<a href='?tag=" .
                        urlencode($tag) .
                        "'>";
                    echo $safe_tag;
                    echo "</a> ";
                }
                echo "</div>";
            }

            // TIMESTAMP
            echo "<div class='timestamp'>";
            echo "Answered on: ";
            echo htmlspecialchars($row["timestamp"]);
            echo "</div>";
            echo "</div>";
        }
        echo "</div>";
    } else {
        echo "<p>No answered questions are available at this time.</p>";
    }
    ?>

</body>

</html>
