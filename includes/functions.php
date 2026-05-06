<?php
// Add utility functions here if needed
function sanitize($data) {
    return htmlspecialchars(strip_tags($data));
}
?>