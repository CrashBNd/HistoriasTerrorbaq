<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

include 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['REQUEST_URI'];
$path = parse_url($path, PHP_URL_PATH);
$path = str_replace('/HistoriasTerrorbaq', '', $path); // Adjust based on your setup

$segments = explode('/', trim($path, '/'));
$endpoint = $segments[0] ?? '';

if ($endpoint === 'api') {
    $resource = $segments[1] ?? '';

    switch ($method) {
        case 'GET':
            if ($resource === 'stories') {
                $query = "SELECT h.*, u.nombre, u.apellidoP FROM historias h JOIN usuarios u ON h.id_usuario = u.id_usuario WHERE h.estado = 'activo'";
                $result = $conn->query($query);
                $stories = [];
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $stories[] = [
                            'id' => $row['id_historia'],
                            'title' => $row['titulo'],
                            'overview' => $row['descripcion'],
                            'poster_path' => 'uploads/' . $row['archivo_pdf'],
                            'pdf' => 'http://localhost/HistoriasTerrorbaq/uploads/' . $row['archivo_pdf'],
                            'vote_average' => 5.0,
                            'vote_count' => 10,
                            'release_date' => $row['fecha_publicacion'],
                            'author' => $row['autor'],
                            'user_name' => $row['nombre'] . ' ' . $row['apellidoP']
                        ];
                    }
                } else {
                    echo json_encode(['error' => 'Error en consulta BD']);
                    exit;
                }
                echo json_encode(['results' => $stories]);
            }
            break;

        case 'POST':
            if ($resource === 'upload') {
                $titulo = $_POST['titulo'];
                $autor = $_POST['autor'];
                $descripcion = $_POST['descripcion'];
                $id_usuario = $_POST['id_usuario']; // From session or token
                $archivo = $_FILES['archivo_pdf'];

                if ($archivo['type'] != 'application/pdf' || $archivo['size'] > 10 * 1024 * 1024) {
                    echo json_encode(['error' => 'Archivo inválido']);
                    exit;
                }

                $target = 'uploads/' . basename($archivo['name']);
                if (move_uploaded_file($archivo['tmp_name'], $target)) {
                    $query = "INSERT INTO historias (id_usuario, titulo, autor, descripcion, archivo_pdf) VALUES (?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param('issss', $id_usuario, $titulo, $autor, $descripcion, $target);
                    $stmt->execute();
                    echo json_encode(['success' => true]);
                }
            } elseif ($resource === 'register') {
                $nombre = $_POST['nombre'];
                $apellidoP = $_POST['apellidoP'];
                $apellidoM = $_POST['apellidoM'];
                $carrera = $_POST['carrera'];
                $semestre = $_POST['semestre'];
                $matricula = $_POST['matricula'];
                $correo = $_POST['correo'];
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

                $query = "INSERT INTO usuarios (id_rol, nombre, apellidoP, apellidoM, carrera, semestre, matricula, correo, password) VALUES (2, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param('ssssisss', $nombre, $apellidoP, $apellidoM, $carrera, $semestre, $matricula, $correo, $password);
                $stmt->execute();
                echo json_encode(['success' => true]);
            } elseif ($resource === 'login') {
                $correo = $_POST['correo'];
                $password = $_POST['password'];

                $query = "SELECT * FROM usuarios WHERE correo = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param('s', $correo);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();

                if ($user && password_verify($password, $user['password'])) {
                    echo json_encode(['success' => true, 'id_usuario' => $user['id_usuario'], 'rol' => $user['id_rol']]);
                } else {
                    echo json_encode(['error' => 'Credenciales inválidas']);
                }
            }
            break;

        case 'DELETE':
            if ($resource === 'admin' && $segments[2] === 'stories') {
                $id = $segments[3];
                // Check if admin (e.g., via session)
                $query = "DELETE FROM historias WHERE id_historia = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param('i', $id);
                $stmt->execute();
                echo json_encode(['success' => true]);
            }
            break;
    }
}
$conn->close();
?>
