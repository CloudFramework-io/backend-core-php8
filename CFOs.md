# CFOs (Cloud Framework Objects)

Los CFOs son un listado de registros de datos almacenados en 'Google Datastore' (que llamaremos en adelante REGISTROS)
y representan información de otros modelos de datos almacenados en servidores como Google Datastore (ds), Google SQL (db) 
o Google Bigquery (bq). A estos modelos de datos que representan los REGISTROS de los CFOs les llamaremos TABLAS (TABLA en singular). 
Los CFOs también contienen información para que una WebApp denominada cfo.html muestre un comportamiento específico 
en sus funciones de conexión con los los servidores donde residen las TABLAS  así como el listado de datos, 
visualización, inserción, modificación o borrado de las mismas. A cada uno de los REGISTROS de los CFOs se le llama CFO 
y el campo 'KeyName' de cada REGISTRO tiene el identificador único en el campo 'KeyName' (nombre del CFO). 
El campo 'type' cuyos valores pueden ser: ds,db o bq definen la tecnología del servidor de la TABLA que está definiendo. 
El campo 'lowCode' del CFO contiene una estructura JSON que determinan los campos del modelo de datos de la TABLA así 
como sus dependencias con otros CFOs. El campo 'interface' es una estructura JSON que que es utilizada por la WebApp cfo.html. 
Por todo esto decimos que los CFOs proporcionan en una formato LOW-CODE una forma de crear aplicaciones vinculadas a TABLAS
y que permite la conexión a los servidores donde residen los datos de las mismas.

## ¿Qué hace un CFO?

Un CFO combina dos aspectos fundamentales:

1. **Definición de Modelo de Datos**: Describe la estructura de una entidad Datastore, tabla SQL o tabla BigQuery (TABLA)
2. **Interfaz Web Automática**: Proporciona configuración completa para la WebApp `cfo.html` que gestiona:
   - Listados con filtros y búsquedas
   - Visualización de registros
   - Formularios de inserción
   - Formularios de modificación
   - Operaciones de borrado y copia
   - Seguridad y permisos de acceso

## Tipos de CFOs

Existen tres tipos de CFOs según la fuente de datos (campo `type`):

- **`ds` (Datastore)**: Define entidades de Google Cloud Datastore (NoSQL)
- **`db` (Database)**: Define tablas de MySQL/Cloud SQL (relacional)
- **`bq` (BigQuery)**: Define tablas de Google BigQuery (analítico)

## La entidad Datastore CloudFrameWorkCFOs

Cada registro CFO en la entidad `CloudFrameWorkCFOs` contiene los siguientes campos:

### Campos de Identificación

La estructura de campos de un CFO se describe a través de los campos de la TABLA CloudFrameWorkCFOs

- **`KeyName`** (keyname) [Clave única] - Identificador único del CFO (ej. CloudFrameWorkCFOs, MP_HIPOTECH_EntityOffices)
- **`DateUpdating`** (datetime) - Fecha y hora de la última actualización del CFO
- **`Title`** (string) - Descripción corta del CFO
- **`Description`** (string) - Descripción larga del CFO
- **`type`** (string) - Tipo de TABLA que define el CFO. Los posibles valores son (ds=Google DataStore,db=Google SQL,bq=Google BigQuery)
- **`Active`** (boolean) - Indica si el CFO está activo (true) o no (false)
- **`GroupName`** (string) - Nombre del grupo al que pretence el CFO
- **`extends`** (string) - ID del CFO del que se extiende el actual CFO
- **`entity`** (string) - Nombre de TABLA en el servidor que contiene los datos. En general suele coincidir con el valor del KeyName
- **`Connections`** (list) - Conexiones permitidas para el CFO. Son los posibles valores el atributo secret
- **`environment`** (string) - Define el Entorno por defecto sobre el que se ejecuta el CFO
- **`status`** (string) - Estado del CFO, indicando el grado de madurez de su definición.
- **`lowCode`** (json) - Estructura JSON para definir el modelo de datos la TABLA.
- **`model`** (json) - Estructura JSON generada a partir del campo lowCode que sirve para facilitar las queries en las TABLAS de datos. Esta estructura es utilizada por el entorno programativo.
- **`securityAndFields`** (json) - Estructura JSON que define propiedades de los campos que será utilizado por la WebApp cfo.html
- **`interface`** (json) - Estructura JSON que define propiedades para el comportamiento de la WebApp cfo.html
- **`events`** (json) - Estructura JSON para definir Hooks y Workflows que se ejecutarán en procesos de creación, modificación o borrado de las TABLAS
- **`CloudFrameworkUser`** (string) - Indica el id del usuario de la plataforma cloud que ha actualizado el CFO por última vez
- **`Owner`** (string) - Indica el id del usuario de la plataforma cloud que es el principal responsable de la definición del CFO
- **`hasExternalWorkflows`** (boolean) - Indica si existen Workflows externos definidos en el CFO [CloudFrameWorkCFOWorkFlows] al margen los workflows que pudieran estar definidos en elcampo [events]
- **`Tags`** (list) - Palabras clave separadas por [,] para facilitar la búsqueda del CFO el la plataforma cloud.
- **`_search`** (list) - Campo interno que contiene palabras clave generadas a partir del resto de otros campos para facilitar la búsqueda del cfo.

Ejemplo de un CFO para definir a la TABLA
### Campos de Tipo y Estado

- **`entity`** (string, indexado): nombre de la TABLA que reside en una infraestructura externa y que contiene los datos a gestionar
- **`type`** (string, indexado): Tipo de CFO con valores: `ds` (Datastore), `db` (Database), `bq` (BigQuery), `mongodb`, `api`
- **`Active`** (boolean, indexado): Indica si el CFO está activo. Solo los CFOs inactivos pueden ser eliminados
- **`status`** (string, indexado): Estado de desarrollo del CFO:
  - "IN PRODUCTION": En producción y estable
  - "IN QA": En fase de pruebas
  - "IN DEVELOPMENT": En desarrollo activo
  - "IN DESIGN": En fase de diseño

### Campos de Herencia y Conexiones

- **`extends`** (string, indexado): KeyName del CFO padre si este CFO hereda de otro
- **`Connections`** (list, indexado): Lista de nombres de conexiones de base de datos permitidas
- **`environment`** (string, indexado): Entorno por defecto a utilizar

### Campos de Metadatos

- **`DateUpdating`** (datetime, indexado): Fecha y hora de la última actualización (valor forzado: now)
- **`CloudFrameworkUser`** (string, indexado, obligatorio): Email del usuario que realizó la última actualización
- **`Owner`** (string, indexado): Email del propietario del CFO
- **`Tags`** (list, indexado): Array de palabras clave separadas por comas para búsquedas
- **`_search`** (list, indexado): Campo de búsqueda interno autogenerado

### Campos de Workflows y Eventos

- **`hasExternalWorkflows`** (boolean, indexado, default: false): Si es true, el CFO buscará workflows externos en la entidad `CloudFrameWorkCFOWorkFlows`
- **`events`** (json): Objeto JSON que define hooks y workflows internos del CFO

### Campos de Definición de Datos (JSON)

#### 1. **`lowCode`** (json)

Es una campo de tipo json que contendrá la siguiente estructura:
- **`name`** Nombre en singular en un lenguaje natural que será utilizado por la WebApp cfo.html
- **`plural`** Nombre en plural en un lenguaje natural que será utilizado por la WebApp cfo.html
- **`ico`** Identificador  de icono que será utilizado por la WebApp cfo.html
- **`description`** Una descripción para uso interno del CFO para entender qué datos va a contener y qué uso se va a hacer de los datos que contendrá la TABLA relacionada
- **`secret`** Campo opcinal que define el identificador de un secreto que contiene los datos de conexión con la infraestructura donde residirá la tabla.
- **`model`** (array de objetos): Array de campos que definen los campos de la tabla {campo1:{elementos del campo},campo2:{elementos del campo},..}
- **`dependencies`** (array de strings): Array strings que contienen el Identificador de otros CFOs con los que está relacionado el CFO Actual.

##### **`lowCode.model`** (json)

El campo model dentro de 'lowCode' es un array de objetos que describen cada uno de los campos de la TABLA que describen. 
Este array de objetvos tiene la estructura: ["<nombre_de_campo>":{"propiedad1":"valor1","propiedad2":"valor2",..}].  Las propiedades 
que tienen los campos son los siguientes:   

- **`name`** (string) Nombre del campo en singungular
- **`type`** (string) Tipo de datos. En función del tipo de TABLA (ds,db,bq) este campo puede contener diferentes valores (string,integer, etc..) 
- **`key`** (boolean) Indica que el campo es clave única en la TABLA 
- **`auto_increment`** (boolean) Indica que el campo es auto_increment (sólo para CFOs de tipo db) 
- **`allow_null`** (boolean) Indica se el campo admite valores nulos 
- **`default`** (mix) Valor por defecto que debe tener el campo en caso de que no se envíe 
- **`index`** (boolean): Indica si el campo va a estar o no indexado para queries más rápidas
- **`minlength`** (integer): Campo opcional para indicar que el contenido del campo debe tener como mínimo una longitud determinada
- **`maxlength`** (integer): Campo opcional para indicar que el contenido del campo debe tener como máximo una longitud determinada
- **`view`** (boolean): Indica si el campo debe aparecer en los listados general de la TABLA
- **`insert`** (boolean): Indica si el campo debe aparecer al insertar una tupla en la TABLA
- **`display`** (boolean): Indica si el campo debe aparecer al visualizar una tupla en la TABLA
- **`update`** (update): Indica si el campo debe aparecer al modificar una tupla en la TABLA
- **`copy`** (update): Indica si el campo debe aparecer al copiar una tupla en la TABLA

###### **`lowCode.model.<campo>.type`** (string)

**Tipos de datos comunes para CFOs de tipo ds (TABLAS DATASTORE)**:

- `keyname`: Clave única del registro
- `string`: Cadena de texto
- `integer` / `int(11)`: Número entero
- `boolean` / `bit(1)`: Valor booleano
- `date`: Campo de tipo Fecha y hora
- `datetime`: Campo de tipo Fecha y hora
- `json`: Campo de tipo  JSON
- `list`: Campo de tipo Array de valores

**Tipos de datos comunes para CFOs de tipo db (TABLAS SQL) **:

- `char(N)`: Campo de tipo string de longitud fija
- `varcha(N)`: Campo de tipo string de longitud variable
- `varbinary(N)`: Campo de tipo binario de longitud variable
- `integer`: Campo de tipo entero
- `int(N)`: Campo de tipo entero de longitud variable
- `decimal(N,M)`: Campo de tipo númerico con N digitos en la parte entera y M dígitos en la parte decimal
- `double`: Campo de tipo  tipo double
- `float`: Campo de tipo  tipo float
- `bit(1)`: Campo de tipo  tipo bit 1/0
- `date`: Campo de tipo fecha
- `datetime`: Campo de tipo Fecha y hora
- `json`: Campo de tipo  JSON
- `text`: Campo de tipo text
- `mediumtext`: Campo de tipo mediumtext
- `longotext`: Campo de tipo longotext
- `blob`: Campo de tipo blob

---

### lowCode para CFOs tipo DB (Database/MySQL)

Los CFOs de tipo `db` describen tablas SQL y requieren una conexión a base de datos.

**Atributos específicos de tipo DB:**

- **`secret`**: **Obligatorio** - Referencia al secret de GCP con la conexión MySQL
  - Formato: `"nombre-secret.db-connection"` o `"NOMBRE_SECRET.connection"`
  - Ejemplo: `"cfo-secrets.db-connection"`, `"HIPOTECH_SECRETS.connection"`

**Estructura del model en tipo DB:**

Cada campo en `model` describe una columna SQL con la siguiente estructura:
```json
{ 
  "lowCode": { 
      "model": {
        "NombreCampo": {
          "name": "Nombre visible",
          "description": "Descripción del campo",
          "type": "tipo_sql",
          "allow_null": true|false,
          "default": "valor_por_defecto"|null,
          "key": true|false,
          "auto_increment": true|false,
          "index": { definición_índice }
        }
      }
   }
}
```

### Principales diferencias entre DB y DS

| Característica | DB (Database/MySQL) | DS (Datastore) |
|----------------|---------------------|----------------|
| **Conexión** | Requiere `secret` obligatorio | No requiere `secret` |
| **Tipos de datos** | SQL estrictos (`varchar(60)`, `int(11)`, etc.) | Booleano simple (true=string, false=number) |
| **Índices** | Soporte completo con índices compuestos | Índice simple (true/false) |
| **Clave primaria** | `key: true` + `auto_increment` opcional | `KeyName` implícito |
| **Valores nulos** | Control explícito con `allow_null` | Implícito según contexto |
| **Flags CRUD** | Definidos en `securityAndFields` | Definidos en el `model` directamente |
| **Campos virtuales** | No tiene sección `virtual` | Sección `virtual` específica |
| **Seguridad** | Definida en `securityAndFields` | Puede estar en `lowCode.security` |
| **Flexibilidad** | Schema rígido (ALTER TABLE) | Schema flexible |
| **Transacciones** | Soporte ACID completo | Transacciones limitadas |
| **Consultas complejas** | SQL completo (JOINs, GROUP BY, etc.) | Consultas más simples |

---

### Cuándo usar cada tipo

**Usa tipo DB (Database) cuando:**
- ✅ Necesites integridad referencial estricta
- ✅ Requieras transacciones ACID
- ✅ Tengas consultas SQL complejas con JOINs
- ✅ El schema sea estable y bien definido
- ✅ Necesites tipos de datos SQL específicos (decimal, date, etc.)
- ✅ Trabajes con datos financieros o contables

**Usa tipo DS (Datastore) cuando:**
- ✅ Necesites alta escalabilidad
- ✅ El schema pueda evolucionar frecuentemente
- ✅ Trabajes con datos semi-estructurados
- ✅ Requieras replicación global automática
- ✅ Las consultas sean simples (por índice o key)
- ✅ Necesites integración nativa con GCP

#### 2. **`securityAndFields`** (json)

Define la seguridad de acceso y configuración de campos para la interfaz web.

**Estructura de `security`**:
```json
{
  "security": {
    "_user_spacenames": ["namespace1", "namespace2"],
    "user_organizations": ["org1", "org2"] o null,
    "user_privileges": ["privilege1", "privilege2"],
    "cfo_locked": true/false,
    "allow_insert": ["privilege"],
    "allow_update": ["privilege"],
    "allow_delete": ["privilege"],
    "allow_display": ["privilege"],
    "allow_copy": ["privilege"],
    "logs": {
      "update": true,
      "delete": true
    },
    "backups": {
      "update": true,
      "delete": true
    }
  }
}
```

**Configuración de campos**:
```json
{
  "fields": {
    "NombreCampo": {
      "name": "Nombre visible",
      "type": "text|select|multiselect|autoselect|boolean|json|html|virtual|...",
      "allow_empty": true/false,
      "defaultvalue": "valor",
      "tab": "nombre_tab",
      "section_class": "col col-4",
      "full_col_width": true/false,
      "disabled": true/false,
      "read_only": true/false,
      "hidden": true/false,
      "rules": [
        {
          "type": "unique|required|email|...",
          "message": "Mensaje de error"
        }
      ],
      "external_values": "ds|db|bq",
      "entity": "NombreEntidad",
      "fields": "campo1,campo2",
      "linked_field": "campoId",
      "external_where": {"campo": "valor"},
      "external_order": "campo ASC"
    }
  }
}
```

**Tipos de campo comunes**:
- `text`: Texto simple
- `html`: Editor HTML
- `json`: Editor JSON
- `select`: Selector simple
- `multiselect`: Selector múltiple
- `autoselect`: Selector con autocompletado
- `boolean`: Checkbox
- `virtual`: Campo calculado/virtual
- `select_icon`: Selector de iconos

#### 4. **`interface`** (json)

Define el comportamiento completo de la WebApp (cfo.html) para gestionar los datos.

**Configuración general**:
```json
{
  "name": "Nombre singular",
  "plural": "Nombre plural",
  "ico": "icono-fontawesome",
  "ecm": "/cfos/NombreCFO",
  "secret": "NOMBRE_SECRET.connection",
  "modal_size": "sm|md|lg|xl"
}
```

**Componentes principales**:

- **`filters`**: Filtros para la vista de listado
  ```json
  {
    "campo": {
      "field": "nombre_campo",
      "field_name": "Etiqueta",
      "type": "select|autocomplete|...",
      "values": [...],
      "defaultvalue": "valor"
    }
  }
  ```

- **`buttons`**: Botones de acción en la interfaz
  ```json
  [
    {
      "title": "Texto del botón",
      "type": "api-insert|external-api|...",
      "api": "URL del API",
      "ico": "icono"
    }
  ]
  ```

- **`tabs`**: Pestañas en el formulario
  ```json
  {
    "main": {
      "title": "Principal",
      "ico": "icono"
    },
    "advanced": {
      "title": "Avanzado",
      "ico": "icono"
    }
  }
  ```

- **`views`**: Diferentes vistas de listado
  ```json
  {
    "default": {
      "name": "Vista por defecto",
      "all_fields": true,
      "server_limit": 100,
      "server_order": "campo ASC",
      "server_where": {"campo": "valor"},
      "server_fields": "campo1,campo2",
      "table_fixed_header": true,
      "conditional_rows_background_color": {
        "default": "#ffffff",
        "fields": [
          {
            "field": "Status",
            "condition": "equals",
            "color": "#00ff00",
            "values": ["activo"]
          }
        ]
      },
      "joins": [
        {
          "cfo": "CFORelacionado",
          "id_field": "campoId",
          "cfo_linked_field": "campoRelacionado",
          "join_type": "LEFT|INNER",
          "fields": "campo1,campo2"
        }
      ],
      "fields": {
        "NombreCampo": {
          "field": "nombre_campo",
          "name": "Etiqueta visible"
        }
      },
      "multiselect": {
        "active": true,
        "menu": [
          {
            "title": "Acción múltiple",
            "type": "cfo-update-fields|external-api",
            "values": {"campo": "valor"}
          }
        ]
      }
    }
  }
  ```

- **`insert_fields`**: Campos en el formulario de inserción
- **`update_fields`**: Campos en el formulario de actualización
- **`display_fields`**: Campos en la vista de detalle
- **`delete_fields`**: Campos mostrados al eliminar
- **`copy_fields`**: Campos incluidos al copiar

- **`profiles`**: Perfiles de interfaz para diferentes roles de usuario
  ```json
  {
    "nombre-perfil": {
      "type": "replace|merge",
      "security": {
        "user_privileges": ["privilege1"]
      },
      "filters": {...},
      "views": {...},
      "buttons": [...]
    }
  }
  ```

- **`hooks`**: Hooks de ciclo de vida
  ```json
  {
    "on.insert": [...],
    "on.update": [...],
    "on.delete": [...]
  }
  ```

### Colores Condicionales de Fila (`conditional_rows_background_color`)

La propiedad `conditional_rows_background_color` permite colorear las filas de un listado en base a condiciones sobre los valores de los campos. Se define dentro de una vista (`views.{nombre_vista}`).

**Formato de colores:**
- `#RRGGBB` - Color RGB estándar (ej: `#CCCCCC`, `#ff0000`)
- `#RRGGBBAA` - Color RGBA con transparencia (ej: `#43db81a0`). Los últimos dos caracteres definen el canal alpha (00=transparente, ff=opaco)

**Prioridad de condiciones:** Cuando se definen múltiples condiciones en `fields[]`, se evalúan en orden y se aplica el color de la **primera condición que sea verdadera**.

#### Atributos principales

| Atributo | Descripción | Ejemplo |
|----------|-------------|---------|
| `default` | Color por defecto cuando ninguna condición se cumple | `"#f5f5f5"` |
| `field_color` | Campo del CFO que contiene el color a aplicar | `"Color"` |
| `fields[]` | Array de condiciones basadas en valores de campo | Ver estructura abajo |
| `external_values` | Tipo de fuente externa para el color (`ds`, `db`, `bq`) | `"ds"` |

#### Modo 1: Color por condiciones (`fields[]`)

```json
"conditional_rows_background_color": {
  "default": "#f5f5f5",
  "fields": [
    {
      "field": "Status",
      "condition": "equals",
      "color": "#d9fba2",
      "values": ["closed", "completed"]
    },
    {
      "field": "Status",
      "condition": "equals",
      "color": "#ffdba1",
      "values": ["pending"]
    },
    {
      "field": "Amount",
      "condition": "lessthan",
      "color": "#ffcccc",
      "value": 0
    }
  ]
}
```

**Atributos de cada condición en `fields[]`:**

| Atributo | Obligatorio | Descripción |
|----------|-------------|-------------|
| `field` | Sí | Nombre del campo a evaluar |
| `condition` | Sí | Condición a aplicar (ver tabla de condiciones) |
| `color` | Sí | Color a aplicar si la condición se cumple |
| `values` | No* | Array de valores a comparar |
| `value` | No* | Valor único a comparar. Soporta sustitución `{{campo}}` |

*Se requiere `values` o `value` excepto para condiciones `empty` y `not_empty`.

**Condiciones disponibles:**

| Condición | Descripción |
|-----------|-------------|
| `equals` | El campo es igual a alguno de los valores |
| `not_equals` | El campo NO es igual a ninguno de los valores |
| `lessthan` | El campo es menor que el valor |
| `lessthanorequals` | El campo es menor o igual que el valor |
| `greaterthan` | El campo es mayor que el valor |
| `greaterthanorequals` | El campo es mayor o igual que el valor |
| `empty` | El campo está vacío o es null |
| `not_empty` | El campo tiene un valor |

**Ejemplo con comparación entre campos:**
```json
{
  "field": "current_stock",
  "condition": "lessthan",
  "color": "#ff0000",
  "value": "{{min_stock}}"
}
```

#### Modo 2: Color desde campo del registro (`field_color`)

Cuando cada registro tiene su propio color almacenado en un campo:

```json
"conditional_rows_background_color": {
  "field_color": "Color"
}
```

#### Modo 3: Color desde entidad relacionada (`external_values`)

Obtiene el color de una tabla relacionada:

```json
"conditional_rows_background_color": {
  "default": "#ebf7fc",
  "external_values": "ds",
  "entity": "CloudFrameWorkNotificationsConfig",
  "linked_field": "KeyName",
  "fields": "Color",
  "condition_field": "ConfigId"
}
```

| Atributo | Descripción |
|----------|-------------|
| `external_values` | Tipo de fuente: `ds` (Datastore), `db` (SQL), `bq` (BigQuery) |
| `entity` | Nombre del CFO que contiene los colores |
| `linked_field` | Campo clave en la entidad externa (ej: `KeyName`, `KeyId`, `id`) |
| `fields` | Campo(s) que contienen el color |
| `condition_field` | Campo del CFO actual que se compara con `linked_field` |

**Documentación completa:** Ver WebPage [CFO conditional_rows_background_color](https://app.cloudframework.app/app.html#__ecm?page=/training/cfos/cfi/views/conditional_rows_background_color)

## Backups de CFOs

Los backups de CFOs se almacenan en el directorio `buckets/backup/` organizado por cliente:

- **`cloudframework/`**: CFOs propietarios de CloudFramework (modelos maestros)
- **Otros directorios** (bnext, alaskadeco, hipotecalia, laworatory, etc.): CFOs de clientes

Cada archivo JSON representa un CFO completo con todos sus campos de configuración.

## Acceso Web a CFOs

La interfaz web para gestionar datos mediante CFOs está disponible en:

```
https://core20.web.app/ajax/cfo.html?api=/cfi/{KeyName}
```

Esta webapp lee la configuración del CFO y genera automáticamente:
- Listados con paginación, filtros y búsqueda
- Formularios de inserción y edición
- Vistas de detalle
- Operaciones CRUD completas

## Dependencias

Los CFOs pueden tener dependencias con otros CFOs (campo `dependencies` en `model`), lo que indica relaciones entre entidades y permite:
- Joins en vistas de listado
- Selects con valores de otras entidades
- Validaciones de integridad referencial

**IMPORTANTE - Regla de Gestión de Dependencias:**

El array `dependencies` DEBE incluir TODOS los CFOs con los que este CFO se relaciona a través de cualquiera de sus campos:

- **Foreign Keys**: Cualquier campo con propiedad `foreign_key` apuntando a otra tabla
- **External Selects**: Cualquier campo en `securityAndFields.fields` con propiedades `external_values` y `entity`
- **Auto-referencias**: Los campos que referencian la misma tabla NO necesitan añadirse a dependencies

El array dependencies debe estar presente en AMBAS ubicaciones:
- `model.dependencies` - Usado por la capa de modelo
- `lowCode.dependencies` - Usado por la interfaz low-code

## Acceso a CFOs desde código PHP

```php
// CFO de Datastore
$data = $this->cfos->ds('CloudFrameWorkUsers')->fetchAll('*');

// CFO de Database
$data = $this->cfos->db('MP_HIPOTECH_EntityOffices')->fetch(['Active' => true], '*');

// CFO de BigQuery
$data = $this->cfos->bq('AnalyticsEvents')->query("SELECT * FROM events WHERE date = CURRENT_DATE()");
```

## Reglas para modificar CFOs

**REGLAS IMPORTANTES al modificar archivos JSON de CFOs:**

1. **Siempre actualizar `DateUpdating`**: Al modificar la estructura de un CFO, DEBE actualizarse el campo `DateUpdating` con fecha y hora actual en formato `YYYY-MM-DD HH:MM:SS`.

2. **Estructura estándar para `organization_id`**: Para CFOs de solución HRMS (o cualquier CFO que referencia organizaciones), el campo `organization_id` en `securityAndFields.fields` debe seguir esta estructura:
   ```json
   "organization_id": {
     "name": "Organización",
     "type": "select",
     "external_values": "db",
     "entity": "MP_HRMS_organizations",
     "linked_field": "id",
     "external_where": { "active": 1 },
     "fields": "id as organization_id,name as organization_name",
     "allow_empty": false,
     "empty_value": "Select Organización HRMS"
   }
   ```

3. **Consistencia en campos similares**: Cuando se establece un patrón para un tipo de campo, aplicar el mismo patrón en todos los CFOs que usen ese campo.

4. **Campos bit(1) en CFOs de database**: Para CFOs de tipo `db`, los campos definidos como `bit(1)` en SQL deben usar `type: "bit"` en `securityAndFields.fields`, con valor por defecto numérico (1 o 0):
   ```json
   "is_active": {
     "name": "Activo",
     "type": "bit",
     "allow_empty": false,
     "defaultvalue": 1
   }
   ```

## Scripts de Backup y Sincronización

CloudFramework proporciona scripts para sincronizar CFOs entre el directorio de backup local y la plataforma cloud remota.
Se requiere que el usuario tenga privilegios de [development-admin] o [development-user]

### Scripts Disponibles

1**Backup from Remote** - Descarga todos los CFOs de la plataforma remota al backup local:
   ```bash
   composer run-script script _backup/cfos/backup-from-remote
   ```
2**Backup from Remote** - Descarga un CFO de la plataforma remota al backup local:
   ```bash
   composer run-script script _backup/cfos/backup-from-remote?id=:idCFO
   ```

3**Insert from Backup** - Inserta un CFO NUEVO a la plataforma remota:
   ```bash
   composer run-script script _backup/cfos/insert-from-backup?id=:idCFO
   ```

4**Update from Backup** - Actualiza un CFO EXISTENTE en la plataforma remota:
   ```bash
   composer run-script script _backup/cfos/update-from-backup?id=:idCFO
   ```

### Workflow para CREAR un nuevo CFO

1. **Crear el CFO**: Crear el archivo JSON con estructura completa
2. **Establecer DateUpdating**: Con timestamp actual
3. **Preguntar al usuario**: Si desea insertarlo en la plataforma remota
4. **Insertar en remoto**: Ejecutar el script insert-from-backup

### Workflow para MODIFICAR un CFO existente

1. **SIEMPRE descargar la última versión primero**:
   ```bash
   php vendor/cloudframework-io/backend-core-php8/runscript.php \
     "_backup/cfos/backup-from-remote?id={CFO_KeyName}"
   ```
   **Este paso es OBLIGATORIO** para evitar sobrescribir cambios hechos por otros usuarios.

2. **Modificar el CFO**: Hacer los cambios necesarios
3. **Actualizar DateUpdating**: Con timestamp actual
4. **Preguntar al usuario**: Si desea actualizar en la plataforma remota
5. **Actualizar en remoto**: Ejecutar el script update-from-backup

## CFI Class (CloudFramework Interface)

La clase **CFI** es una utilidad PHP para generar modelos JSON que instruyen a la WebApp `cfo.html` en CLOUD Platform. Proporciona una interfaz fluida para construir formularios dinámicos, campos, botones y pestañas.

**Ubicación**: `buckets/cloudframework.io/api-dev/erp/class/CFIDevelopment.php`

**Documentación detallada**: Ver `buckets/backups/CFOs/CFI.md`

**Uso rápido:**
```php
// En clases API que extienden CFAPI2026, CFI está disponible vía $this->cfi

// Establecer título de aplicación
$this->cfi->setTile("Mi Título de Aplicación");

// Añadir campos
$this->cfi->field('name')->title('Nombre')->value($name);
$this->cfi->field('email')->title('Email')->value($email)->disabled();
$this->cfi->field('status')->select('Estado', $options, 'id', 'name')->value($selected);
$this->cfi->field('data')->json('Datos JSON')->value($jsonData)->disabled();

// Añadir botones
$this->cfi->button('Enviar')->color('info')->url($submitUrl, 'POST');

// Retornar el modelo JSON
return $this->addReturnData($this->cfi->returnData());
```

**Tipos de campo disponibles:**
- `field()` - Campo de texto (por defecto)
- `date()` / `datetime()` - Selector de fecha/hora
- `textarea()` - Texto multilínea
- `html()` - Editor HTML
- `boolean()` - Checkbox
- `select()` - Dropdown
- `checkbox()` - Grupo de checkboxes
- `json()` - Visualización JSON
- `iframe()` - Contenido iFrame
- `externalTable()` - Datos tabulares
- `serverDocuments()` - Subida de archivos
- `publicImage()` - Subida de imágenes
- `virtual()` - Elementos interactivos