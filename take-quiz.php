<?php
session_start();
require_once 'config/database.php';
require_once 'classes/Gamification.php';

if(!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header('Location: quizzes.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$gamification = new Gamification($db);

$quiz_id = $_GET['id'];

// Get quiz details
$query = "SELECT * FROM personality_quizzes WHERE id = :id AND is_active = TRUE LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $quiz_id);
$stmt->execute();
$quiz = $stmt->fetch();

if(!$quiz) {
    header('Location: quizzes.php');
    exit();
}

// Check if already completed
$query = "SELECT id FROM user_quiz_results WHERE user_id = :user_id AND quiz_id = :quiz_id LIMIT 1";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->bindParam(':quiz_id', $quiz_id);
$stmt->execute();

if($stmt->rowCount() > 0) {
    $_SESSION['error'] = 'You have already completed this quiz!';
    header('Location: quizzes.php');
    exit();
}

// Get questions
$query = "SELECT * FROM quiz_questions WHERE quiz_id = :quiz_id ORDER BY display_order ASC";
$stmt = $db->prepare($query);
$stmt->bindParam(':quiz_id', $quiz_id);
$stmt->execute();
$questions = $stmt->fetchAll();

$success = '';
$error = '';

// Handle submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $answers = [];
    foreach($questions as $question) {
        if(isset($_POST['question_' . $question['id']])) {
            $answers[$question['id']] = $_POST['question_' . $question['id']];
        }
    }
    
    $answers_json = json_encode($answers);
    
    // Save results
    $query = "INSERT INTO user_quiz_results (user_id, quiz_id, answers, score) 
              VALUES (:user_id, :quiz_id, :answers, :score)";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->bindParam(':quiz_id', $quiz_id);
    $stmt->bindParam(':answers', $answers_json);
    $score = count($answers) * 10; // Simple scoring
    $stmt->bindParam(':score', $score);
    
    if($stmt->execute()) {
        // Award points
        $gamification->awardPoints($_SESSION['user_id'], $quiz['points_reward'], 'quiz_completed', 'Completed: ' . $quiz['title']);
        
        $_SESSION['success'] = 'Quiz completed! You earned ' . $quiz['points_reward'] . ' points! 🎉';
        header('Location: quizzes.php');
        exit();
    } else {
        $error = 'Failed to save quiz results';
    }
}

include 'views/header-pink.php';
?>

<div class="page-content">
    <div class="container-narrow">
        <div class="card">
            <div style="text-align: center; margin-bottom: 2rem;">
                <div style="font-size: 4rem; margin-bottom: 1rem;">💫</div>
                <h1 style="margin-bottom: 0.5rem;"><?php echo htmlspecialchars($quiz['title']); ?></h1>
                <p style="color: var(--text-gray);">
                    <?php echo htmlspecialchars($quiz['description']); ?>
                </p>
                <div class="level-badge" style="margin-top: 1rem;">
                    Earn <?php echo $quiz['points_reward']; ?> Points
                </div>
            </div>
            
            <?php if($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="take-quiz.php?id=<?php echo $quiz_id; ?>">
                <?php foreach($questions as $index => $question): ?>
                <div style="margin-bottom: 2rem; padding: 1.5rem; background: rgba(255, 107, 157, 0.05); border-radius: 15px;">
                    <strong style="color: var(--primary-pink); display: block; margin-bottom: 1rem; font-size: 1.1rem;">
                        <?php echo ($index + 1); ?>. <?php echo htmlspecialchars($question['question']); ?>
                    </strong>
                    
                    <?php if($question['question_type'] == 'multiple_choice'): ?>
                        <?php $options = json_decode($question['options'], true); ?>
                        <?php foreach($options as $option): ?>
                        <label style="display: block; padding: 1rem; background: var(--card-bg); border-radius: 10px; margin-bottom: 0.5rem; cursor: pointer; transition: all 0.3s; border: 2px solid transparent;">
                            <input type="radio" name="question_<?php echo $question['id']; ?>" value="<?php echo htmlspecialchars($option); ?>" required style="margin-right: 0.5rem;">
                            <span style="color: var(--text-white);"><?php echo htmlspecialchars($option); ?></span>
                        </label>
                        <?php endforeach; ?>
                    <?php elseif($question['question_type'] == 'scale'): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <?php for($i = 1; $i <= 10; $i++): ?>
                            <label style="text-align: center; cursor: pointer;">
                                <input type="radio" name="question_<?php echo $question['id']; ?>" value="<?php echo $i; ?>" required style="display: block; margin: 0 auto 0.5rem;">
                                <span style="color: var(--text-gray); font-size: 0.9rem;"><?php echo $i; ?></span>
                            </label>
                            <?php endfor; ?>
                        </div>
                    <?php else: ?>
                        <textarea name="question_<?php echo $question['id']; ?>" rows="4" required style="width: 100%; padding: 1rem; border-radius: 10px; background: var(--card-bg); color: var(--text-white); border: 2px solid var(--border-color);"></textarea>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                
                <button type="submit" class="btn-primary btn-block sparkle">
                    Complete Quiz & Earn Points 🎯
                </button>
            </form>
        </div>
    </div>
</div>

<style>
label:has(input[type="radio"]):hover {
    background: rgba(255, 107, 157, 0.2) !important;
    border-color: var(--primary-pink) !important;
}

label:has(input[type="radio"]:checked) {
    background: rgba(255, 107, 157, 0.3) !important;
    border-color: var(--primary-pink) !important;
    box-shadow: 0 0 20px rgba(255, 107, 157, 0.3);
}
</style>

<?php include 'views/footer.php'; ?>