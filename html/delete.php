<?php
include "../db./conn.php";

if (isset($_GET['id'])) {
    $id = intval($_GET['id']); // make sure it's an integer

    // delete query
    $sql = "DELETE FROM register WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);

    if (mysqli_stmt_execute($stmt)) {
        echo "<script>
                alert('Record deleted successfully!');
                window.location.href = 'index.php';
              </script>";
    } else {
        echo "<script>
                alert('Error deleting record: " . mysqli_error($conn) . "');
                window.location.href = 'index.php';
              </script>";
    }

    mysqli_stmt_close($stmt);
}
mysqli_close($conn);
?>
