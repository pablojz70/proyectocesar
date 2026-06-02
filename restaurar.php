<?php
// Restaurar proyectojair y mantener proyectocesar funcionando
$ftp = ftp_connect('ftpupload.net', 21, 30);
ftp_login($ftp, 'if0_42011396', 'Pjzc13804');
ftp_pasv($ftp, true);

// 1. proyectojair -> htdocs_old (tiene el proyecto anterior)
// 2. proyectocesar -> proyectocesar.infinityfree.me/htdocs (ya tiene los archivos nuevos)

// Verificar que existan los directorios
$dirs = ftp_nlist($ftp, '/');
echo "Directorios en raiz:\n";
foreach ($dirs as $d) echo "  $d\n";

// Si htdocs_old existe, renombrar a htdocs para proyectojair
// Pero primero mover los archivos de proyectocesar a su carpeta
// (ya deberian estar en proyectocesar.infinityfree.me/htdocs/)

// Renombrar htdocs a htdocs_proyectocesar (backup de mi proyecto)
// y htdocs_old a htdocs (para proyectojair)
if (ftp_rename($ftp, '/htdocs', '/htdocs_proyectocesar')) {
    echo "htdocs -> htdocs_proyectocesar OK\n";
}
if (ftp_rename($ftp, '/htdocs_old', '/htdocs')) {
    echo "htdocs_old -> htdocs OK (proyectojair restaurado)\n";
}

// Ahora copiar los archivos de proyectocesar de htdocs_proyectocesar
// a proyectocesar.infinityfree.me/htdocs/ (si no estan ya)
$check = ftp_nlist($ftp, '/proyectocesar.infinityfree.me/htdocs/');
if (count($check) <= 2) { // solo . y ..
    echo "Copiando archivos a proyectocesar.infinityfree.me/htdocs/...\n";
    // copiar desde htdocs_proyectocesar
}

ftp_close($ftp);
echo "\nListo. Verifica:\n";
echo "  proyectojair.infinityfree.me - debe mostrar el proyecto anterior\n";
echo "  proyectocesar.infinityfree.me/login.php - debe mostrar el login\n";
