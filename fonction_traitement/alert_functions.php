<?php
function setAlertMessage($type, $message) {
    $_SESSION['alert'] = [
        'type' => $type,
        'message' => $message
    ];
}

function displayAlert() {
    if (isset($_SESSION['alert'])) {
        $type = $_SESSION['alert']['type'];
        $message = $_SESSION['alert']['message'];
        
        // Définir les icônes et couleurs selon le type
        $icon = '';
        $title = '';
        $color = '';
        
        switch($type) {
            case 'success':
                $icon = 'success';
                $title = 'Succès!';
                $color = '#28a745';
                break;
            case 'error':
                $icon = 'error';
                $title = 'Erreur!';
                $color = '#dc3545';
                break;
            case 'warning':
                $icon = 'warning';
                $title = 'Attention!';
                $color = '#ffc107';
                break;
            case 'info':
                $icon = 'info';
                $title = 'Information';
                $color = '#17a2b8';
                break;
        }
        
        echo "
        <script>
            Swal.fire({
                title: '$title',
                text: '$message',
                icon: '$icon',
                confirmButtonColor: '$color',
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: false,
                position: 'top-end',
                toast: true
            });
        </script>";
        
        unset($_SESSION['alert']);
    }
}
?> 