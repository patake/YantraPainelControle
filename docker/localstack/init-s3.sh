#!/bin/bash
# Cria o bucket local do painel com as mesmas regras do bucket da AWS.
# O LocalStack roda este script quando o S3 fica pronto (pasta ready.d).
# O LocalStack community não guarda os objetos entre reinícios: o script recria o bucket vazio.
set -euo pipefail

BUCKET="${S3_BUCKET:-yantra-painel}"
REGION="${AWS_DEFAULT_REGION:-sa-east-1}"

awslocal s3api create-bucket \
  --bucket "$BUCKET" \
  --region "$REGION" \
  --create-bucket-configuration LocationConstraint="$REGION"

# Sem acesso público: o navegador e o agente usam só URLs assinadas.
awslocal s3api put-public-access-block \
  --bucket "$BUCKET" \
  --public-access-block-configuration BlockPublicAcls=true,IgnorePublicAcls=true,BlockPublicPolicy=true,RestrictPublicBuckets=true

# Criptografia dos objetos (SSE-S3).
awslocal s3api put-bucket-encryption \
  --bucket "$BUCKET" \
  --server-side-encryption-configuration '{"Rules":[{"ApplyServerSideEncryptionByDefault":{"SSEAlgorithm":"AES256"}}]}'

# previews/ expira em 1 dia. agent-releases/ não expira.
awslocal s3api put-bucket-lifecycle-configuration \
  --bucket "$BUCKET" \
  --lifecycle-configuration '{"Rules":[{"ID":"apagar-previews","Filter":{"Prefix":"previews/"},"Status":"Enabled","Expiration":{"Days":1}}]}'

echo "Bucket $BUCKET pronto em $REGION."
