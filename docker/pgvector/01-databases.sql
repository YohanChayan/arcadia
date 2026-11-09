-- The vector store's databases, on the container's first start only. The index lives in
-- POSTGRES_DB; the tests get their own, so a test run never empties the index. pgvector is
-- enabled in both; the migrations also ensure it, which is all production relies on.

CREATE EXTENSION IF NOT EXISTS vector;

CREATE DATABASE arcadia_vectors_testing;

\connect arcadia_vectors_testing

CREATE EXTENSION IF NOT EXISTS vector;
