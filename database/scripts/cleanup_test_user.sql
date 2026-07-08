-- ============================================================
-- DIAGNÓSTICO: cuántos datos tiene cada usuario
-- ============================================================

-- Ver cuántos tokens tiene cada usuario (los más pesados primero)
SELECT
    u.id,
    u.name,
    u.email,
    COUNT(pat.id) AS tokens_total
FROM users u
LEFT JOIN personal_access_tokens pat
    ON pat.tokenable_type = 'App\\Models\\User' AND pat.tokenable_id = u.id
GROUP BY u.id, u.name, u.email
ORDER BY tokens_total DESC;

-- Ver cuántos documentos ha subido cada usuario
SELECT
    u.id,
    u.name,
    u.email,
    COUNT(d.id) AS documentos_total
FROM users u
LEFT JOIN documents d ON d.uploaded_by = u.id
GROUP BY u.id, u.name, u.email
ORDER BY documentos_total DESC;


-- ============================================================
-- LIMPIEZA DE TOKENS VIEJOS
-- Conserva solo los 3 tokens más recientes por usuario.
-- Cambia @user_id por el ID real del usuario de prueba.
-- ============================================================

-- Paso 1: Ver cuántos tokens tiene el usuario de prueba
SET @user_id = 1;  -- << CAMBIA ESTO AL ID DEL USUARIO DE PRUEBA

SELECT COUNT(*) AS tokens_usuario_prueba
FROM personal_access_tokens
WHERE tokenable_type = 'App\\Models\\User'
  AND tokenable_id = @user_id;

-- Paso 2: Eliminar todos menos los 3 más recientes
DELETE FROM personal_access_tokens
WHERE tokenable_type = 'App\\Models\\User'
  AND tokenable_id = @user_id
  AND id NOT IN (
      SELECT id FROM (
          SELECT id
          FROM personal_access_tokens
          WHERE tokenable_type = 'App\\Models\\User'
            AND tokenable_id = @user_id
          ORDER BY created_at DESC
          LIMIT 3
      ) AS keep_tokens
  );

SELECT ROW_COUNT() AS tokens_eliminados;


-- ============================================================
-- LIMPIEZA DE DOCUMENTOS DE PRUEBA (OPCIONAL)
-- Solo ejecutar si los documentos de prueba ya no son necesarios.
-- ============================================================

-- Ver documentos del usuario de prueba por estado
SELECT status, COUNT(*) AS total
FROM documents
WHERE uploaded_by = @user_id
GROUP BY status;

-- Borrar todos los documentos del usuario de prueba en estado 'revisado'
-- (los ya revisados normalmente ya no son necesarios para pruebas)
-- DESCOMENTA SOLO SI ESTÁS SEGURO:
/*
DELETE FROM documents
WHERE uploaded_by = @user_id
  AND status = 'revisado';

SELECT ROW_COUNT() AS documentos_eliminados;
*/


-- ============================================================
-- VERIFICACIÓN FINAL
-- ============================================================
SELECT
    (SELECT COUNT(*) FROM personal_access_tokens
     WHERE tokenable_type = 'App\\Models\\User' AND tokenable_id = @user_id) AS tokens_restantes,
    (SELECT COUNT(*) FROM documents WHERE uploaded_by = @user_id) AS documentos_restantes;
