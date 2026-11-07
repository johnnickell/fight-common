FROM python:3.13.17-slim@sha256:fe14fd57a4c0361110eaa0c284112b189750bcb43fb04bb5d1ee7b7744027ccf

WORKDIR /app

COPY requirements.txt /tmp/requirements.txt

# The base must already contain fixed OS and pip-vendored trust before retrieval.
RUN python -c 'import ssl; import pip._vendor.certifi as c; assert tuple(map(int, c.__version__.split("."))) >= (2026, 10, 19); assert not any(v == "emSign ECC Root CA - G3" for cert in ssl.create_default_context().get_ca_certs() for rdn in cert["subject"] for k, v in rdn if k == "commonName")' \
    && python -m pip install --no-cache-dir --requirement /tmp/requirements.txt \
    && python -c 'import certifi; assert tuple(map(int, certifi.__version__.split("."))) >= (2026, 10, 19)'
