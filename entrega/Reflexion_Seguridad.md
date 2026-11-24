# Actividad 5: reflexión de seguridad

Cada integrante completa **solo su sección**, reemplazando el texto entre sus marcadores
`<!-- INICIO ... -->` y `<!-- FIN ... -->`. No toques las secciones de los demás.

---

<!-- INICIO JOSEPH (preguntas 1 y 2) -->
_Pendiente: Joseph_
<!-- FIN JOSEPH -->

---

<!-- INICIO DIANA (preguntas 3 y 4) -->
_Pendiente: Diana_
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
