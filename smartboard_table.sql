-- This script only contains the table creation statements and does not fully represent the table in the database. Do not use it as a backup.

-- Table Definition
CREATE TABLE "public"."cache" (
    "key" varchar(255) NOT NULL,
    "value" text NOT NULL,
    "expiration" int8 NOT NULL,
    PRIMARY KEY ("key")
);


-- Indices
CREATE INDEX cache_expiration_index ON public.cache USING btree (expiration);

-- This script only contains the table creation statements and does not fully represent the table in the database. Do not use it as a backup.

-- Table Definition
CREATE TABLE "public"."cache_locks" (
    "key" varchar(255) NOT NULL,
    "owner" varchar(255) NOT NULL,
    "expiration" int8 NOT NULL,
    PRIMARY KEY ("key")
);


-- Indices
CREATE INDEX cache_locks_expiration_index ON public.cache_locks USING btree (expiration);

-- This script only contains the table creation statements and does not fully represent the table in the database. Do not use it as a backup.

-- Sequence and defined type
CREATE SEQUENCE IF NOT EXISTS department_module_id_seq;

-- Table Definition
CREATE TABLE "public"."department_module" (
    "id" int8 NOT NULL DEFAULT nextval('department_module_id_seq'::regclass),
    "department_id" int8 NOT NULL,
    "module_code" varchar(255) NOT NULL,
    "created_at" timestamp(0),
    "updated_at" timestamp(0),
    CONSTRAINT "department_module_department_id_foreign" FOREIGN KEY ("department_id") REFERENCES "public"."departments"("id") ON DELETE CASCADE,
    PRIMARY KEY ("id")
);


-- Indices
CREATE UNIQUE INDEX department_module_department_id_module_code_unique ON public.department_module USING btree (department_id, module_code);

-- This script only contains the table creation statements and does not fully represent the table in the database. Do not use it as a backup.

-- Sequence and defined type
CREATE SEQUENCE IF NOT EXISTS departments_id_seq;

-- Table Definition
CREATE TABLE "public"."departments" (
    "id" int8 NOT NULL DEFAULT nextval('departments_id_seq'::regclass),
    "name" varchar(100) NOT NULL,
    "created_at" timestamp(0),
    "updated_at" timestamp(0),
    PRIMARY KEY ("id")
);


-- Indices
CREATE UNIQUE INDEX departments_name_unique ON public.departments USING btree (name);

-- This script only contains the table creation statements and does not fully represent the table in the database. Do not use it as a backup.

-- Sequence and defined type
CREATE SEQUENCE IF NOT EXISTS employees_id_seq;

-- Table Definition
CREATE TABLE "public"."employees" (
    "id" int8 NOT NULL DEFAULT nextval('employees_id_seq'::regclass),
    "user_id" int8 NOT NULL,
    "department_id" int8,
    "position_struktural_id" int8,
    "position_fungsional_id" int8,
    "employment_status" varchar(100),
    "is_cltp" bool NOT NULL DEFAULT false,
    "contract_start_date" date,
    "contract_end_date" date,
    "nis" varchar(50),
    "nik" varchar(16),
    "npwp" varchar(50),
    "education_level" varchar(50),
    "birth_date" date,
    "gender" varchar(20),
    "phone_number" varchar(30),
    "marital_status" varchar(50),
    "target_okr" text,
    "pipeline_permissions" jsonb,
    "kpi_achieved_m1" numeric(5,2) NOT NULL DEFAULT '0'::numeric,
    "kpi_achieved_m2" numeric(5,2) NOT NULL DEFAULT '0'::numeric,
    "created_at" timestamp(0),
    "updated_at" timestamp(0),
    "deleted_at" timestamp(0),
    "region_id" int8,
    CONSTRAINT "employees_user_id_foreign" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE CASCADE,
    CONSTRAINT "employees_department_id_foreign" FOREIGN KEY ("department_id") REFERENCES "public"."departments"("id") ON DELETE SET NULL,
    CONSTRAINT "employees_position_struktural_id_foreign" FOREIGN KEY ("position_struktural_id") REFERENCES "public"."positions"("id") ON DELETE SET NULL,
    CONSTRAINT "employees_position_fungsional_id_foreign" FOREIGN KEY ("position_fungsional_id") REFERENCES "public"."positions"("id") ON DELETE SET NULL,
    CONSTRAINT "employees_region_id_foreign" FOREIGN KEY ("region_id") REFERENCES "public"."regions"("id") ON DELETE SET NULL,
    PRIMARY KEY ("id")
);


-- Indices
CREATE UNIQUE INDEX employees_user_id_unique ON public.employees USING btree (user_id)
CREATE UNIQUE INDEX employees_nik_unique ON public.employees USING btree (nik)
CREATE UNIQUE INDEX employees_npwp_unique ON public.employees USING btree (npwp)
CREATE INDEX employees_department_id_index ON public.employees USING btree (department_id)
CREATE INDEX employees_position_struktural_id_index ON public.employees USING btree (position_struktural_id)
CREATE INDEX employees_position_fungsional_id_index ON public.employees USING btree (position_fungsional_id)
CREATE INDEX employees_region_id_index ON public.employees USING btree (region_id);

-- This script only contains the table creation statements and does not fully represent the table in the database. Do not use it as a backup.

-- Sequence and defined type
CREATE SEQUENCE IF NOT EXISTS error_logs_id_seq;

-- Table Definition
CREATE TABLE "public"."error_logs" (
    "id" int8 NOT NULL DEFAULT nextval('error_logs_id_seq'::regclass),
    "error_code" varchar(16) NOT NULL,
    "type" varchar(20) NOT NULL DEFAULT 'exception'::character varying,
    "status" int2,
    "severity" varchar(20) NOT NULL DEFAULT 'error'::character varying,
    "message" text NOT NULL,
    "exception_class" varchar(255),
    "stack_trace" text,
    "file" varchar(255),
    "line" int4,
    "column" int4,
    "url" varchar(2048),
    "route_name" varchar(255),
    "method" varchar(10),
    "user_id" int8,
    "user_email" varchar(255),
    "user_name" varchar(255),
    "context" json,
    "resolved" bool NOT NULL DEFAULT false,
    "resolved_at" timestamp(0),
    "created_at" timestamp(0),
    "updated_at" timestamp(0),
    CONSTRAINT "error_logs_user_id_foreign" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE SET NULL,
    PRIMARY KEY ("id")
);


-- Indices
CREATE INDEX error_logs_type_index ON public.error_logs USING btree (type)
CREATE INDEX error_logs_status_index ON public.error_logs USING btree (status)
CREATE INDEX error_logs_severity_index ON public.error_logs USING btree (severity)
CREATE INDEX error_logs_resolved_index ON public.error_logs USING btree (resolved)
CREATE INDEX error_logs_created_at_index ON public.error_logs USING btree (created_at)
CREATE UNIQUE INDEX error_logs_error_code_unique ON public.error_logs USING btree (error_code);

-- This script only contains the table creation statements and does not fully represent the table in the database. Do not use it as a backup.

-- Sequence and defined type
CREATE SEQUENCE IF NOT EXISTS failed_jobs_id_seq;

-- Table Definition
CREATE TABLE "public"."failed_jobs" (
    "id" int8 NOT NULL DEFAULT nextval('failed_jobs_id_seq'::regclass),
    "uuid" varchar(255) NOT NULL,
    "connection" varchar(255) NOT NULL,
    "queue" varchar(255) NOT NULL,
    "payload" text NOT NULL,
    "exception" text NOT NULL,
    "failed_at" timestamp(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY ("id")
);


-- Indices
CREATE INDEX failed_jobs_connection_queue_failed_at_index ON public.failed_jobs USING btree (connection, queue, failed_at)
CREATE UNIQUE INDEX failed_jobs_uuid_unique ON public.failed_jobs USING btree (uuid);

-- This script only contains the table creation statements and does not fully represent the table in the database. Do not use it as a backup.

-- Table Definition
CREATE TABLE "public"."job_batches" (
    "id" varchar(255) NOT NULL,
    "name" varchar(255) NOT NULL,
    "total_jobs" int4 NOT NULL,
    "pending_jobs" int4 NOT NULL,
    "failed_jobs" int4 NOT NULL,
    "failed_job_ids" text NOT NULL,
    "options" text,
    "cancelled_at" int4,
    "created_at" int4 NOT NULL,
    "finished_at" int4,
    PRIMARY KEY ("id")
);

-- This script only contains the table creation statements and does not fully represent the table in the database. Do not use it as a backup.

-- Sequence and defined type
CREATE SEQUENCE IF NOT EXISTS jobs_id_seq;

-- Table Definition
CREATE TABLE "public"."jobs" (
    "id" int8 NOT NULL DEFAULT nextval('jobs_id_seq'::regclass),
    "queue" varchar(255) NOT NULL,
    "payload" text NOT NULL,
    "attempts" int2 NOT NULL,
    "reserved_at" int4,
    "available_at" int4 NOT NULL,
    "created_at" int4 NOT NULL,
    PRIMARY KEY ("id")
);


-- Indices
CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);

-- This script only contains the table creation statements and does not fully represent the table in the database. Do not use it as a backup.

-- Sequence and defined type
CREATE SEQUENCE IF NOT EXISTS migrations_id_seq;

-- Table Definition
CREATE TABLE "public"."migrations" (
    "id" int4 NOT NULL DEFAULT nextval('migrations_id_seq'::regclass),
    "migration" varchar(255) NOT NULL,
    "batch" int4 NOT NULL,
    PRIMARY KEY ("id")
);

-- This script only contains the table creation statements and does not fully represent the table in the database. Do not use it as a backup.

-- Table Definition
CREATE TABLE "public"."model_has_permissions" (
    "permission_id" int8 NOT NULL,
    "model_type" varchar(255) NOT NULL,
    "model_id" int8 NOT NULL,
    CONSTRAINT "model_has_permissions_permission_id_foreign" FOREIGN KEY ("permission_id") REFERENCES "public"."permissions"("id") ON DELETE CASCADE,
    PRIMARY KEY ("permission_id","model_id","model_type")
);


-- Indices
CREATE INDEX model_has_permissions_model_id_model_type_index ON public.model_has_permissions USING btree (model_id, model_type);

-- This script only contains the table creation statements and does not fully represent the table in the database. Do not use it as a backup.

-- Table Definition
CREATE TABLE "public"."model_has_roles" (
    "role_id" int8 NOT NULL,
    "model_type" varchar(255) NOT NULL,
    "model_id" int8 NOT NULL,
    CONSTRAINT "model_has_roles_role_id_foreign" FOREIGN KEY ("role_id") REFERENCES "public"."roles"("id") ON DELETE CASCADE,
    PRIMARY KEY ("role_id","model_id","model_type")
);


-- Indices
CREATE INDEX model_has_roles_model_id_model_type_index ON public.model_has_roles USING btree (model_id, model_type);

-- This script only contains the table creation statements and does not fully represent the table in the database. Do not use it as a backup.

-- Sequence and defined type
CREATE SEQUENCE IF NOT EXISTS passkeys_id_seq;

-- Table Definition
CREATE TABLE "public"."passkeys" (
    "id" int8 NOT NULL DEFAULT nextval('passkeys_id_seq'::regclass),
    "user_id" int8 NOT NULL,
    "name" varchar(255) NOT NULL,
    "credential_id" varchar(255) NOT NULL,
    "credential" json NOT NULL,
    "last_used_at" timestamp(0),
    "created_at" timestamp(0),
    "updated_at" timestamp(0),
    CONSTRAINT "passkeys_user_id_foreign" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE CASCADE,
    PRIMARY KEY ("id")
);


-- Indices
CREATE INDEX passkeys_user_id_index ON public.passkeys USING btree (user_id)
CREATE UNIQUE INDEX passkeys_credential_id_unique ON public.passkeys USING btree (credential_id);

-- This script only contains the table creation statements and does not fully represent the table in the database. Do not use it as a backup.

-- Table Definition
CREATE TABLE "public"."password_reset_tokens" (
    "email" varchar(255) NOT NULL,
    "token" varchar(255) NOT NULL,
    "created_at" timestamp(0),
    PRIMARY KEY ("email")
);

-- This script only contains the table creation statements and does not fully represent the table in the database. Do not use it as a backup.

-- Sequence and defined type
CREATE SEQUENCE IF NOT EXISTS permissions_id_seq;

-- Table Definition
CREATE TABLE "public"."permissions" (
    "id" int8 NOT NULL DEFAULT nextval('permissions_id_seq'::regclass),
    "name" varchar(255) NOT NULL,
    "guard_name" varchar(255) NOT NULL,
    "created_at" timestamp(0),
    "updated_at" timestamp(0),
    PRIMARY KEY ("id")
);


-- Indices
CREATE UNIQUE INDEX permissions_name_guard_name_unique ON public.permissions USING btree (name, guard_name);

-- This script only contains the table creation statements and does not fully represent the table in the database. Do not use it as a backup.

-- Sequence and defined type
CREATE SEQUENCE IF NOT EXISTS cards_id_seq;

-- Table Definition
CREATE TABLE "public"."pmo_cards" (
    "id" int8 NOT NULL DEFAULT nextval('cards_id_seq'::regclass),
    "title" varchar(255) NOT NULL,
    "status" varchar(255) NOT NULL DEFAULT 'Active'::character varying,
    "stage_id" int8 NOT NULL,
    "employee_id" int8,
    "contact_id" int8,
    "output_id" int8,
    "nominal_value" numeric(15,2) NOT NULL DEFAULT '0'::numeric,
    "fiscal_year" int4 NOT NULL DEFAULT 2026,
    "sumber_proyeksi" varchar(255),
    "detail_sumber_proyeksi" text,
    "pitching_date" date,
    "communication_method" varchar(255),
    "projection_month" varchar(255),
    "projection_week" varchar(255),
    "projection_status" varchar(255),
    "drive_link" varchar(255),
    "negative_reason" varchar(255),
    "manager_verified" bool NOT NULL DEFAULT false,
    "s3_document_path" varchar(255),
    "history_logs" json,
    "follow_ups" json,
    "archived_at" timestamp(0),
    "created_at" timestamp(0),
    "updated_at" timestamp(0),
    CONSTRAINT "cards_stage_id_foreign" FOREIGN KEY ("stage_id") REFERENCES "public"."pmo_pipeline_stages"("id") ON DELETE CASCADE,
    CONSTRAINT "cards_employee_id_foreign" FOREIGN KEY ("employee_id") REFERENCES "public"."employees"("id") ON DELETE SET NULL,
    CONSTRAINT "cards_contact_id_foreign" FOREIGN KEY ("contact_id") REFERENCES "public"."pmo_contacts"("id") ON DELETE SET NULL,
    CONSTRAINT "cards_output_id_foreign" FOREIGN KEY ("output_id") REFERENCES "public"."pmo_outputs"("id") ON DELETE SET NULL,
    PRIMARY KEY ("id")
);


-- Indices
CREATE UNIQUE INDEX cards_pkey ON public.pmo_cards USING btree (id);

-- This script only contains the table creation statements and does not fully represent the table in the database. Do not use it as a backup.

-- Sequence and defined type
CREATE SEQUENCE IF NOT EXISTS contact_positions_id_seq;

-- Table Definition
CREATE TABLE "public"."pmo_contact_positions" (
    "id" int8 NOT NULL DEFAULT nextval('contact_positions_id_seq'::regclass),
    "kelompok_jabatan" varchar(255),
    "eselon" varchar(255),
    "name" varchar(255) NOT NULL,
    "tingkat" varchar(255),
    "created_at" timestamp(0),
    "updated_at" timestamp(0),
    PRIMARY KEY ("id")
);


-- Indices
CREATE UNIQUE INDEX contact_positions_pkey ON public.pmo_contact_positions USING btree (id)
CREATE UNIQUE INDEX contact_positions_name_unique ON public.pmo_contact_positions USING btree (name);

-- This script only contains the table creation statements and does not fully represent the table in the database. Do not use it as a backup.

-- Sequence and defined type
CREATE SEQUENCE IF NOT EXISTS contacts_id_seq;

-- Table Definition
CREATE TABLE "public"."pmo_contacts" (
    "id" int8 NOT NULL DEFAULT nextval('contacts_id_seq'::regclass),
    "name" varchar(255) NOT NULL,
    "sumber" varchar(255) NOT NULL,
    "region_id" int8,
    "pic_employee_id" int8,
    "category" varchar(255) NOT NULL,
    "regency_id" int8,
    "phone" varchar(255),
    "email" varchar(255),
    "created_at" timestamp(0),
    "updated_at" timestamp(0),
    "contact_position_id" int8,
    "partnership_contact_id" int8,
    "opd_id" int8,
    CONSTRAINT "contacts_region_id_foreign" FOREIGN KEY ("region_id") REFERENCES "public"."regions"("id") ON DELETE SET NULL,
    CONSTRAINT "contacts_pic_employee_id_foreign" FOREIGN KEY ("pic_employee_id") REFERENCES "public"."employees"("id") ON DELETE SET NULL,
    CONSTRAINT "contacts_regency_id_foreign" FOREIGN KEY ("regency_id") REFERENCES "public"."regencies"("id") ON DELETE SET NULL,
    CONSTRAINT "contacts_contact_position_id_foreign" FOREIGN KEY ("contact_position_id") REFERENCES "public"."pmo_contact_positions"("id") ON DELETE SET NULL,
    CONSTRAINT "contacts_opd_id_foreign" FOREIGN KEY ("opd_id") REFERENCES "public"."pmo_opds"("id") ON DELETE SET NULL,
    PRIMARY KEY ("id")
);


-- Indices
CREATE UNIQUE INDEX contacts_pkey ON public.pmo_contacts USING btree (id)
CREATE UNIQUE INDEX contacts_partnership_contact_id_unique ON public.pmo_contacts USING btree (partnership_contact_id)
CREATE INDEX contacts_opd_id_index ON public.pmo_contacts USING btree (opd_id)
CREATE INDEX contacts_regency_id_index ON public.pmo_contacts USING btree (regency_id)
CREATE INDEX contacts_region_id_index ON public.pmo_contacts USING btree (region_id);

-- This script only contains the table creation statements and does not fully represent the table in the database. Do not use it as a backup.

-- Sequence and defined type
CREATE SEQUENCE IF NOT EXISTS divisions_id_seq;

-- Table Definition
CREATE TABLE "public"."pmo_divisions" (
    "id" int8 NOT NULL DEFAULT nextval('divisions_id_seq'::regclass),
    "code" varchar(50) NOT NULL,
    "name" varchar(150) NOT NULL,
    "created_at" timestamp(0),
    "updated_at" timestamp(0),
    PRIMARY KEY ("id")
);


-- Indices
CREATE UNIQUE INDEX divisions_pkey ON public.pmo_divisions USING btree (id)
CREATE UNIQUE INDEX divisions_code_unique ON public.pmo_divisions USING btree (code);

-- This script only contains the table creation statements and does not fully represent the table in the database. Do not use it as a backup.

-- Table Definition
CREATE TABLE "public"."pmo_employee_regions" (
    "employee_id" int8 NOT NULL,
    "region_id" int8 NOT NULL,
    CONSTRAINT "employee_regions_employee_id_foreign" FOREIGN KEY ("employee_id") REFERENCES "public"."employees"("id") ON DELETE CASCADE,
    CONSTRAINT "employee_regions_region_id_foreign" FOREIGN KEY ("region_id") REFERENCES "public"."regions"("id") ON DELETE CASCADE,
    PRIMARY KEY ("employee_id","region_id")
);


-- Indices
CREATE UNIQUE INDEX employee_regions_pkey ON public.pmo_employee_regions USING btree (employee_id, region_id);

-- This script only contains the table creation statements and does not fully represent the table in the database. Do not use it as a backup.

-- Table Definition
CREATE TABLE "public"."pmo_opd_urusan" (
    "opd_id" int8 NOT NULL,
    "urusan_id" int8 NOT NULL,
    CONSTRAINT "opd_urusan_opd_id_foreign" FOREIGN KEY ("opd_id") REFERENCES "public"."pmo_opds"("id") ON DELETE CASCADE,
    CONSTRAINT "opd_urusan_urusan_id_foreign" FOREIGN KEY ("urusan_id") REFERENCES "public"."pmo_urusans"("id") ON DELETE CASCADE,
    PRIMARY KEY ("opd_id","urusan_id")
);


-- Indices
CREATE UNIQUE INDEX opd_urusan_pkey ON public.pmo_opd_urusan USING btree (opd_id, urusan_id);

-- This script only contains the table creation statements and does not fully represent the table in the database. Do not use it as a backup.

-- Sequence and defined type
CREATE SEQUENCE IF NOT EXISTS opds_id_seq;

-- Table Definition
CREATE TABLE "public"."pmo_opds" (
    "id" int8 NOT NULL DEFAULT nextval('opds_id_seq'::regclass),
    "regency_id" int8 NOT NULL,
    "name" varchar(255) NOT NULL,
    "created_at" timestamp(0),
    "updated_at" timestamp(0),
    CONSTRAINT "opds_regency_id_foreign" FOREIGN KEY ("regency_id") REFERENCES "public"."regencies"("id") ON DELETE CASCADE,
    PRIMARY KEY ("id")
);


-- Indices
CREATE UNIQUE INDEX opds_pkey ON public.pmo_opds USING btree (id)
CREATE INDEX opds_regency_id_index ON public.pmo_opds USING btree (regency_id);

-- This script only contains the table creation statements and does not fully represent the table in the database. Do not use it as a backup.

-- Sequence and defined type
CREATE SEQUENCE IF NOT EXISTS outputs_id_seq;

-- Table Definition
CREATE TABLE "public"."pmo_outputs" (
    "id" int8 NOT NULL DEFAULT nextval('outputs_id_seq'::regclass),
    "division_id" int8 NOT NULL,
    "service_center_id" int8 NOT NULL,
    "category_code" varchar(50) NOT NULL,
    "output_name" varchar(255) NOT NULL,
    "pelaksana" varchar(255),
    "status" bool NOT NULL DEFAULT true,
    "sequence" int4 NOT NULL,
    "generated_code" varchar(100) NOT NULL,
    "created_at" timestamp(0),
    "updated_at" timestamp(0),
    CONSTRAINT "outputs_division_id_foreign" FOREIGN KEY ("division_id") REFERENCES "public"."pmo_divisions"("id") ON DELETE CASCADE,
    CONSTRAINT "outputs_service_center_id_foreign" FOREIGN KEY ("service_center_id") REFERENCES "public"."pmo_service_centers"("id") ON DELETE CASCADE,
    CONSTRAINT "outputs_category_code_foreign" FOREIGN KEY ("category_code") REFERENCES "public"."pmo_product_categories"("code") ON DELETE CASCADE,
    PRIMARY KEY ("id")
);


-- Indices
CREATE UNIQUE INDEX outputs_pkey ON public.pmo_outputs USING btree (id)
CREATE UNIQUE INDEX outputs_generated_code_unique ON public.pmo_outputs USING btree (generated_code);

-- This script only contains the table creation statements and does not fully represent the table in the database. Do not use it as a backup.

-- Sequence and defined type
CREATE SEQUENCE IF NOT EXISTS pipeline_profiles_id_seq;

-- Table Definition
CREATE TABLE "public"."pmo_pipeline_profiles" (
    "id" int8 NOT NULL DEFAULT nextval('pipeline_profiles_id_seq'::regclass),
    "name" varchar(255) NOT NULL,
    "pipeline_name" varchar(255) NOT NULL,
    "is_active" bool NOT NULL DEFAULT false,
    "created_at" timestamp(0),
    "updated_at" timestamp(0),
    PRIMARY KEY ("id")
);


-- Indices
CREATE UNIQUE INDEX pipeline_profiles_pkey ON public.pmo_pipeline_profiles USING btree (id);

-- This script only contains the table creation statements and does not fully represent the table in the database. Do not use it as a backup.

-- Sequence and defined type
CREATE SEQUENCE IF NOT EXISTS pipeline_stages_id_seq;

-- Table Definition
CREATE TABLE "public"."pmo_pipeline_stages" (
    "id" int8 NOT NULL DEFAULT nextval('pipeline_stages_id_seq'::regclass),
    "pipeline_profile_id" int8 NOT NULL,
    "stage_name" varchar(255) NOT NULL,
    "order_index" int4 NOT NULL DEFAULT 0,
    "is_active" bool NOT NULL DEFAULT true,
    "created_at" timestamp(0),
    "updated_at" timestamp(0),
    CONSTRAINT "pipeline_stages_pipeline_profile_id_foreign" FOREIGN KEY ("pipeline_profile_id") REFERENCES "public"."pmo_pipeline_profiles"("id") ON DELETE CASCADE,
    PRIMARY KEY ("id")
);


-- Indices
CREATE UNIQUE INDEX pipeline_stages_pkey ON public.pmo_pipeline_stages USING btree (id);

-- This script only contains the table creation statements and does not fully represent the table in the database. Do not use it as a backup.

-- Sequence and defined type
CREATE SEQUENCE IF NOT EXISTS product_categories_id_seq;

-- Table Definition
CREATE TABLE "public"."pmo_product_categories" (
    "id" int8 NOT NULL DEFAULT nextval('product_categories_id_seq'::regclass),
    "code" varchar(50) NOT NULL,
    "name" varchar(150) NOT NULL,
    "created_at" timestamp(0),
    "updated_at" timestamp(0),
    PRIMARY KEY ("id")
);


-- Indices
CREATE UNIQUE INDEX product_categories_pkey ON public.pmo_product_categories USING btree (id)
CREATE UNIQUE INDEX product_categories_code_unique ON public.pmo_product_categories USING btree (code);

-- This script only contains the table creation statements and does not fully represent the table in the database. Do not use it as a backup.

-- Sequence and defined type
CREATE SEQUENCE IF NOT EXISTS service_centers_id_seq;

-- Table Definition
CREATE TABLE "public"."pmo_service_centers" (
    "id" int8 NOT NULL DEFAULT nextval('service_centers_id_seq'::regclass),
    "division_id" int8 NOT NULL,
    "code" varchar(50) NOT NULL,
    "full_code" varchar(50) NOT NULL,
    "name" varchar(150) NOT NULL,
    "created_at" timestamp(0),
    "updated_at" timestamp(0),
    CONSTRAINT "service_centers_division_id_foreign" FOREIGN KEY ("division_id") REFERENCES "public"."pmo_divisions"("id") ON DELETE CASCADE,
    PRIMARY KEY ("id")
);


-- Indices
CREATE UNIQUE INDEX service_centers_pkey ON public.pmo_service_centers USING btree (id)
CREATE UNIQUE INDEX service_centers_full_code_unique ON public.pmo_service_centers USING btree (full_code);

-- This script only contains the table creation statements and does not fully represent the table in the database. Do not use it as a backup.

-- Sequence and defined type
CREATE SEQUENCE IF NOT EXISTS urusans_id_seq;

-- Table Definition
CREATE TABLE "public"."pmo_urusans" (
    "id" int8 NOT NULL DEFAULT nextval('urusans_id_seq'::regclass),
    "code" varchar(255) NOT NULL,
    "urusan" varchar(255) NOT NULL,
    "created_at" timestamp(0),
    "updated_at" timestamp(0),
    PRIMARY KEY ("id")
);


-- Indices
CREATE UNIQUE INDEX urusans_pkey ON public.pmo_urusans USING btree (id)
CREATE UNIQUE INDEX urusans_code_unique ON public.pmo_urusans USING btree (code);

-- This script only contains the table creation statements and does not fully represent the table in the database. Do not use it as a backup.

-- Sequence and defined type
CREATE SEQUENCE IF NOT EXISTS positions_id_seq;

-- Table Definition
CREATE TABLE "public"."positions" (
    "id" int8 NOT NULL DEFAULT nextval('positions_id_seq'::regclass),
    "name" varchar(100) NOT NULL,
    "type" varchar(50) NOT NULL DEFAULT 'Fungsional'::character varying,
    "created_at" timestamp(0),
    "updated_at" timestamp(0),
    PRIMARY KEY ("id")
);


-- Indices
CREATE UNIQUE INDEX positions_name_unique ON public.positions USING btree (name);

-- This script only contains the table creation statements and does not fully represent the table in the database. Do not use it as a backup.

-- Sequence and defined type
CREATE SEQUENCE IF NOT EXISTS provinces_id_seq;

-- Table Definition
CREATE TABLE "public"."provinces" (
    "id" int8 NOT NULL DEFAULT nextval('provinces_id_seq'::regclass),
    "name" varchar(150) NOT NULL,
    "code" varchar(10),
    "created_at" timestamp(0),
    "updated_at" timestamp(0),
    PRIMARY KEY ("id")
);


-- Indices
CREATE UNIQUE INDEX provinces_name_unique ON public.provinces USING btree (name)
CREATE UNIQUE INDEX provinces_code_unique ON public.provinces USING btree (code);

-- This script only contains the table creation statements and does not fully represent the table in the database. Do not use it as a backup.

-- Sequence and defined type
CREATE SEQUENCE IF NOT EXISTS regencies_id_seq;

-- Table Definition
CREATE TABLE "public"."regencies" (
    "id" int8 NOT NULL DEFAULT nextval('regencies_id_seq'::regclass),
    "province_id" int8 NOT NULL,
    "name" varchar(150) NOT NULL,
    "code" varchar(10),
    "type" varchar(20) NOT NULL DEFAULT 'Kabupaten'::character varying,
    "created_at" timestamp(0),
    "updated_at" timestamp(0),
    "region_id" int8,
    CONSTRAINT "regencies_province_id_foreign" FOREIGN KEY ("province_id") REFERENCES "public"."provinces"("id") ON DELETE CASCADE,
    CONSTRAINT "regencies_region_id_foreign" FOREIGN KEY ("region_id") REFERENCES "public"."regions"("id") ON DELETE SET NULL,
    PRIMARY KEY ("id")
);


-- Indices
CREATE UNIQUE INDEX regencies_code_unique ON public.regencies USING btree (code)
CREATE INDEX regencies_province_id_index ON public.regencies USING btree (province_id)
CREATE INDEX regencies_region_id_index ON public.regencies USING btree (region_id);

-- This script only contains the table creation statements and does not fully represent the table in the database. Do not use it as a backup.

-- Sequence and defined type
CREATE SEQUENCE IF NOT EXISTS regions_id_seq;

-- Table Definition
CREATE TABLE "public"."regions" (
    "id" int8 NOT NULL DEFAULT nextval('regions_id_seq'::regclass),
    "name" varchar(100) NOT NULL,
    "code" varchar(20),
    "created_at" timestamp(0),
    "updated_at" timestamp(0),
    PRIMARY KEY ("id")
);


-- Indices
CREATE UNIQUE INDEX regions_name_unique ON public.regions USING btree (name)
CREATE UNIQUE INDEX regions_code_unique ON public.regions USING btree (code);

-- This script only contains the table creation statements and does not fully represent the table in the database. Do not use it as a backup.

-- Table Definition
CREATE TABLE "public"."role_has_permissions" (
    "permission_id" int8 NOT NULL,
    "role_id" int8 NOT NULL,
    CONSTRAINT "role_has_permissions_permission_id_foreign" FOREIGN KEY ("permission_id") REFERENCES "public"."permissions"("id") ON DELETE CASCADE,
    CONSTRAINT "role_has_permissions_role_id_foreign" FOREIGN KEY ("role_id") REFERENCES "public"."roles"("id") ON DELETE CASCADE,
    PRIMARY KEY ("permission_id","role_id")
);

-- This script only contains the table creation statements and does not fully represent the table in the database. Do not use it as a backup.

-- Sequence and defined type
CREATE SEQUENCE IF NOT EXISTS roles_id_seq;

-- Table Definition
CREATE TABLE "public"."roles" (
    "id" int8 NOT NULL DEFAULT nextval('roles_id_seq'::regclass),
    "name" varchar(255) NOT NULL,
    "guard_name" varchar(255) NOT NULL,
    "created_at" timestamp(0),
    "updated_at" timestamp(0),
    PRIMARY KEY ("id")
);


-- Indices
CREATE UNIQUE INDEX roles_name_guard_name_unique ON public.roles USING btree (name, guard_name);

-- This script only contains the table creation statements and does not fully represent the table in the database. Do not use it as a backup.

-- Table Definition
CREATE TABLE "public"."sessions" (
    "id" varchar(255) NOT NULL,
    "user_id" int8,
    "ip_address" varchar(45),
    "user_agent" text,
    "payload" text NOT NULL,
    "last_activity" int4 NOT NULL,
    PRIMARY KEY ("id")
);


-- Indices
CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id)
CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);

-- This script only contains the table creation statements and does not fully represent the table in the database. Do not use it as a backup.

-- Sequence and defined type
CREATE SEQUENCE IF NOT EXISTS users_id_seq;

-- Table Definition
CREATE TABLE "public"."users" (
    "id" int8 NOT NULL DEFAULT nextval('users_id_seq'::regclass),
    "name" varchar(255) NOT NULL,
    "email" varchar(255) NOT NULL,
    "email_verified_at" timestamp(0),
    "password" varchar(255) NOT NULL,
    "remember_token" varchar(100),
    "created_at" timestamp(0),
    "updated_at" timestamp(0),
    "two_factor_secret" text,
    "two_factor_recovery_codes" text,
    "two_factor_confirmed_at" timestamp(0),
    "department_id" int8,
    CONSTRAINT "users_department_id_foreign" FOREIGN KEY ("department_id") REFERENCES "public"."departments"("id") ON DELETE SET NULL,
    PRIMARY KEY ("id")
);


-- Indices
CREATE UNIQUE INDEX users_email_unique ON public.users USING btree (email);

