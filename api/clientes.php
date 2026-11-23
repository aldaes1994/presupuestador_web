<?php
require_once __DIR__ . "/auth.php";
$idUsuario = requerirUsuarioApi();
require_once __DIR__ . "/conexion.php";   // <-- ESTA LÍNEA FALTABA

header("Content-Type: application/json; charset=utf-8");

$metodo = $_SERVER["REQUEST_METHOD"];

// --- POST: Crear un cliente nuevo ---
if ($metodo === "POST") {
    $datos = json_decode(file_get_contents("php://input"), true);
    if (!is_array($datos)) {
        http_response_code(400);
        echo json_encode(["error" => "El cuerpo de la solicitud no es válido"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $nombre = trim((string)($datos["nombre"] ?? ""));
    $apellido = trim((string)($datos["apellido"] ?? ""));
    $telefono = trim((string)($datos["telefono"] ?? ""));
    $email = trim((string)($datos["email"] ?? ""));
    $direccion = trim((string)($datos["direccion"] ?? ""));

    if ($nombre === "") {
        http_response_code(400);
        echo json_encode(["error" => "El nombre es obligatorio"], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $stmt = $conexion->prepare(
            "INSERT INTO cliente (nombre, apellido, telefono, email, direccion, id_usuario)
             VALUES (:nombre, :apellido, :telefono, :email, :direccion, :id_usuario)"
        );
        $stmt->execute([
            ":nombre" => $nombre,
            ":apellido" => $apellido,
            ":telefono" => $telefono,
            ":email" => $email,
            ":direccion" => $direccion,
            ":id_usuario" => $idUsuario
        ]);

        http_response_code(201);
        echo json_encode(["id" => (int)$conexion->lastInsertId()], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (PDOException $error) {
        error_log("Error al guardar cliente: " . $error->getMessage());
        http_response_code(500);
        echo json_encode(["error" => "No se pudo guardar el cliente"], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// --- GET: Listar clientes (con búsqueda opcional) ---
if ($metodo === "GET") {
    $busqueda = trim((string)($_GET["search"] ?? $_GET["q"] ?? ""));

    $sql = "SELECT id, nombre, apellido, telefono, email, direccion
            FROM cliente
            WHERE id_usuario = :id_usuario";
    $parametros = [":id_usuario" => $idUsuario];

    if ($busqueda !== "") {
        $sql .= " AND (
                    nombre LIKE :busqueda_nombre
                    OR apellido LIKE :busqueda_apellido
                    OR CONCAT(nombre, ' ', apellido) LIKE :busqueda_nombre_completo
                    OR email LIKE :busqueda_email
                    OR telefono LIKE :busqueda_telefono
                )";
        $termino = "%" . $busqueda . "%";
        $parametros[":busqueda_nombre"] = $termino;
        $parametros[":busqueda_apellido"] = $termino;
        $parametros[":busqueda_nombre_completo"] = $termino;
        $parametros[":busqueda_email"] = $termino;
        $parametros[":busqueda_telefono"] = $termino;
    }

    $sql .= " ORDER BY apellido, nombre";
    $stmt = $conexion->prepare($sql);
    $stmt->execute($parametros);

    echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
    exit;
}

// --- Cualquier otro método ---
http_response_code(405);
header("Allow: GET, POST");
echo json_encode(["error" => "Método no permitido"], JSON_UNESCAPED_UNICODE);