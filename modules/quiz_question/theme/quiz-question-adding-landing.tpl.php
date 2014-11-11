<ul class="admin-list quiz-type-list">
  <?php foreach ($question_types as $name => $question_type): ?>
    <?php /* @var $question_type \Drupal\quiz_question\Entity\QuestionType */ ?>
    <li>
      <span class="label">
        <?php echo l($question_type->label, "quiz-question/add/{$name}"); ?>
      </span>

      <?php if ($question_type->description): ?>
        <div class="description">
          <?php echo $question_type->description; ?>
        </div>
      <?php endif; ?>
    </li>
  <?php endforeach; ?>
</ul>
