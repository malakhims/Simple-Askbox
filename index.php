<?php
session_start();

// usually in config.php but this is one of my older scripts and is tiny so i never moved this
//can move into config.php if you want.

include "config.php";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get question from form
    $question = $_POST["question"];

    // Check if the question already exists
    $check_query = "SELECT * FROM questions WHERE question = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("s", $question);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $formresults = "This question has already been submitted!";
    } else {
        // Generate a unique 4-character slug
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        do {
            $slug = '';
            for ($i = 0; $i < 4; $i++) {
                $slug .= $chars[rand(0, strlen($chars)-1)];
            }
            // Check uniqueness
            $slug_check_stmt = $conn->prepare("SELECT id FROM questions WHERE slug = ?");
            $slug_check_stmt->bind_param("s", $slug);
            $slug_check_stmt->execute();
            $slug_check_result = $slug_check_stmt->get_result();
        } while ($slug_check_result->num_rows > 0);
        $slug_check_stmt->close();

        // Insert question with slug
        $sql = "INSERT INTO questions (question, slug, visible, timestamp) VALUES (?, ?, 'n', NOW())";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ss", $question, $slug);

            if ($stmt->execute()) {
                $formresults = "Your question has been submitted!";

                function sanitize_for_discord($text) {
                    // 1. Escape Discord markdown
                    // Characters to escape: \ * _ ~ ` > | [ ] ( )
                    $text = preg_replace('/([\\\\*_\~`>|\\[\\]()])/', '\\\\$1', $text);

                    // 2. Neutralize mass mentions
                    $text = str_ireplace('@everyone', '@ｅveryone', $text);
                    $text = str_ireplace('@here', '@ｈere', $text);

                    // 3. Neutralize user mentions (<@1234567890> or <@!1234567890>)
                    $text = preg_replace('/<@!?[0-9]+>/', '<＠user>', $text);

                    return $text;
                }

                // Send Discord webhook with url if you want
                $webhook_url = "xxx";
                $message = "New Question Submitted:\nQuestion: $question\nSlug: $slug";
                $data = ["content" => $message];

                $curl = curl_init($webhook_url);
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen(json_encode($data))
                ]);
                
                curl_exec($curl);
                curl_close($curl);
            } else {
                $formresults = "Your form was not submitted. Maybe try again? " . $stmt->error;
            }

            $stmt->close();
        } else {
            $formresults = "Form submission failed: " . $conn->error;
        }
    }

    $check_stmt->close();
}

// Fetch visible questions ordered by timestamp DESC
$result = $conn->query("SELECT * FROM questions WHERE visible = 'y' ORDER BY timestamp DESC");
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

    
  </style>
</head>
<body>
    

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <div class="form-group">
            <!--the MIKU  image it's just from tenor-->
            <img src="https://media.tenor.com/ouQzDmgC9CwAAAAi/miku-vocaloid.gif" style="height: 400px;">
            <br>
            <label><b>Ask and Submissions</b></label>
            <br>
            <input style="width:50%;height:70px;border-radius: 5px;" type="text" name="question" class="form-control" required>
        </div>
        <div class="form-group">
            <input type="submit" class="cutiepie" value="Submit">
        </div>
    </form>

    <div class="message">
        <?php echo isset($formresults) ? $formresults : ''; ?>
    </div>
    
    <h2>Questions Answered Thus Far</h2>
  
   <?php
    if ($result->num_rows > 0) {
        echo "<div class='ask-container'>";
        
        while ($row = $result->fetch_assoc()) {
            $question_id = $row["id"]; // Assume 'id' is the unique identifier column in your table
        
            echo "<div class='ask-item' id='question-$question_id'>";
            // Use htmlspecialchars for the question (user input)
            echo "<div class='question'><strong>Q:</strong> " . htmlspecialchars($row["question"]) . "</div>";
            // Directly render the answer to allow HTML
            echo "<div class='answer'><strong>A:</strong> " . $row["answer"] . "</div>";
            echo "<div class='timestamp'>Answered on: " . htmlspecialchars($row["timestamp"]) . "</div>";
            echo "</div>";
        }
        
        echo "</div>";
    } else {
        echo "<p>No answered questions are available at this time.</p>";
    }
    ?>
    

</body>
</html>

