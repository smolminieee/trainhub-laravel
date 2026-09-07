<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback PDF | TrainHub Al Amin</title>
    <link rel="stylesheet" href="assets/css/feedback.css?v=<?= file_exists(public_path('assets/css/feedback.css')) ? filemtime(public_path('assets/css/feedback.css')) : time(); ?>">
</head>
<body class="print-body">
<main class="print-report">
    <?php if (!$responseForm): ?>
        <section class="print-card"><h1>Feedback form not found</h1></section>
    <?php else: ?>
        <section class="print-card print-title-card">
            <h1><?= e($responseForm['title']) ?></h1>
            <p><?= e(feedbackTypeLabel((string)$responseForm['feedbackType'])) ?> · <?= e($responseForm['courseName'] ?: '-') ?> · <?= e($responseForm['sessionName'] ?: $responseForm['sessionID']) ?></p>
            <p>Generated on <?= date('d M Y, h:i A') ?> · View: <?= e(ucfirst($privacyMode)) ?></p>
        </section>

        <section class="print-card">
            <h2>Category Average</h2>
            <table class="print-table">
                <thead><tr><th>Category</th><th>Rating Answers</th><th>Average</th></tr></thead>
                <tbody>
                    <?php foreach ($categorySummary as $summary): ?>
                        <tr><td><?= e($summary['categoryName']) ?></td><td><?= e($summary['ratingCount']) ?></td><td><?= e($summary['averageRating'] ?: '-') ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="print-card">
            <h2>Responses</h2>
            <?php if (empty($responseGroups)): ?>
                <p>No responses yet.</p>
            <?php else: ?>
                <?php $printNo = 1; foreach ($responseGroups as $group): ?>
                    <?php $overall = count($group['ratings']) ? round(array_sum($group['ratings']) / count($group['ratings']), 2) : '-'; ?>
                    <div class="print-response-block">
                        <h3>
                            <?= $privacyMode === 'details' ? e($group['displayName'] ?: $group['respondentKey']) : 'Anonymous Response ' . $printNo ?>
                            <span>Overall: <?= e($overall) ?></span>
                        </h3>
                        <p>
                            <?= e($group['sourceLabel']) ?> · <?= e(ucwords(str_replace('_', ' ', $group['respondentType']))) ?>

                        </p>
                        <?php foreach ($group['categories'] as $category): ?>
                            <?php $catAvg = count($category['ratings']) ? round(array_sum($category['ratings']) / count($category['ratings']), 2) : '-'; ?>
                            <table class="print-table small-print-table">
                                <thead><tr><th colspan="2"><?= e($category['categoryName']) ?> · Avg <?= e($catAvg) ?></th></tr></thead>
                                <tbody>
                                <?php foreach ($category['answers'] as $answer): ?>
                                    <tr>
                                        <td><?= e($answer['questionText']) ?></td>
                                        <td>
                                            <?php if ($answer['questionType'] === 'rating'): ?>
                                                <?= e($answer['rating'] . ' / 5') ?>
                                            <?php elseif ($answer['questionType'] === 'image' && isImagePath($answer['comment'] ?? '')): ?>
                                                <img class="print-answer-image" src="<?= e($answer['comment']) ?>" alt="Uploaded image">
                                            <?php else: ?>
                                                <?= e($answer['comment'] ?: '-') ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endforeach; ?>
                    </div>
                <?php $printNo++; endforeach; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</main>
<script>
window.addEventListener('load', function () { window.print(); });
</script>

<script>
document.addEventListener('change', function (event) {
    const input = event.target.closest('.answer-image-upload input[type="file"]');
    if (!input) return;
    const upload = input.closest('.answer-image-upload');
    const fileLabel = upload ? upload.querySelector('.answer-image-file') : null;
    if (!fileLabel) return;
    fileLabel.textContent = input.files && input.files.length ? input.files[0].name : 'No image selected';
});
</script>
</body>
</html>
