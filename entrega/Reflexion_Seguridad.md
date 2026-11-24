# Actividad 5: reflexión de seguridad

Cada integrante completa **solo su sección**, reemplazando el texto entre sus marcadores
`<!-- INICIO ... -->` y `<!-- FIN ... -->`. No toques las secciones de los demás.

---

<!-- INICIO JOSEPH (preguntas 1 y 2) -->
_Pendiente: Joseph_
<!-- FIN JOSEPH -->

---

<!-- INICIO DIANA (preguntas 3 y 4) -->
**3. ¿Por qué las Policies son adecuadas para operaciones CRUD?** Una Policy agrupa por modelo (`viewAny`, `view`, `create`, `update`, `delete`) las reglas que sí dependen del recurso. Se descubre automáticamente por convención, se invoca con `Gate::authorize` y centraliza la lógica, de modo que no queda dispersa en los controladores.

**4. ¿Cuál es la diferencia entre RBAC y ABAC?** RBAC decide por el rol o permiso asignado (`estudiantes.editar`). ABAC decide por atributos del usuario, del recurso y del contexto (carrera, sede, propiedad, estado activo, horario). En el taller se combinan: el permiso RBAC es necesario pero no suficiente.
<!-- FIN DIANA -->

---

<!-- INICIO FRANKLIN (preguntas 5 y 6) -->
**5. ¿Por qué `estudiantes.editar` no debería permitir modificar a cualquier estudiante?** El permiso habilita la operación, pero no determina sobre cuáles registros puede ejecutarse. Un profesor de ITI no debe modificar estudiantes de ISW solo por tener ese permiso. La Policy combina RBAC con atributos del usuario y del registro: en esta parte exige la misma carrera y, en la Actividad 4, se agregará la misma sede. Así se evita el acceso horizontal indebido a registros ajenos.

**6. ¿Qué riesgo existe si el registro público permite enviar el rol del usuario?** Una persona podría registrarse como administrador y obtener privilegios que no le corresponden. El backend debe validar y utilizar únicamente los campos permitidos del registro público; los roles y permisos deben asignarse mediante código de confianza o una operación administrativa autorizada, nunca directamente desde los datos enviados por el cliente.
<!-- FIN FRANKLIN -->

---

<!-- INICIO EMESIS (preguntas 7 y 8) -->
_Pendiente: Emesis_
<!-- FIN EMESIS -->

---

<!-- INICIO JAIRO (discusión 19.2) -->
_Pendiente: Jairo_
<!-- FIN JAIRO -->
