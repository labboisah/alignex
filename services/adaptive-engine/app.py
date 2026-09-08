import os
import secrets
from typing import Annotated, Literal

from fastapi import Depends, FastAPI, HTTPException, Request
from fastapi.security import APIKeyHeader
from pydantic import BaseModel, ConfigDict, Field, model_validator
from engine import evaluate

app = FastAPI(docs_url=None, redoc_url=None, openapi_url=None)
header = APIKeyHeader(name="X-AlignEx-Engine-Key", auto_error=False)


def authenticate(key: Annotated[str | None, Depends(header)]):
    expected = os.getenv("ADAPTIVE_ENGINE_SECRET", "")
    if len(expected) < 32:
        raise HTTPException(503, "Engine authentication is not configured")
    if key is None or not secrets.compare_digest(key, expected):
        raise HTTPException(401, "Unauthorized")


class Strict(BaseModel):
    model_config = ConfigDict(extra="forbid", strict=True, allow_inf_nan=False)


class Item(Strict):
    id: str = Field(min_length=1, max_length=64)
    a: float = Field(gt=0, le=4)
    b: float = Field(ge=-6, le=6)
    area: str = Field(min_length=1, max_length=80)
    topic: str | None = Field(max_length=64)
    eligible: bool


class Response(Strict):
    id: str = Field(min_length=1, max_length=64)
    correct: bool


class Policy(Strict):
    min_questions: int = Field(ge=1, le=1000)
    max_questions: int = Field(ge=1, le=1000)
    min_per_area: int = Field(ge=1, le=1000)
    target_sd: float = Field(gt=0, le=2)
    cutpoint: float | None = Field(ge=-6, le=6)


class Evaluation(Strict):
    protocol: Literal["alignex-shadow-v1"]
    request_id: str = Field(min_length=1, max_length=64)
    state_version: int = Field(ge=0)
    calibration_fingerprint: str = Field(pattern=r"^[a-f0-9]{64}$")
    items: list[Item] = Field(min_length=1, max_length=10000)
    responses: list[Response] = Field(max_length=1000)
    areas: list[str] = Field(min_length=1, max_length=1000)
    required_topics: list[str] = Field(max_length=1000)
    policy: Policy

    @model_validator(mode="after")
    def references(self):
        ids = {item.id for item in self.items}
        used = [r.id for r in self.responses]
        if len(ids) != len(self.items) or len(set(used)) != len(used) or not set(used) <= ids:
            raise ValueError("Duplicate or unknown item")
        if len(set(self.areas)) != len(self.areas) or any(i.area not in self.areas for i in self.items):
            raise ValueError("Invalid area")
        if not set(self.required_topics) <= {i.topic for i in self.items}:
            raise ValueError("Unknown required topic")
        if self.policy.min_questions > self.policy.max_questions or len(used) > self.policy.max_questions:
            raise ValueError("Invalid question limits")
        if len(self.areas) * self.policy.min_per_area > self.policy.max_questions:
            raise ValueError("Coverage cannot fit the question limit")
        return self


@app.middleware("http")
async def body_limit(request: Request, call_next):
    from starlette.responses import JSONResponse
    size = 0
    chunks = []
    async for chunk in request.stream():
        size += len(chunk)
        if size > 2_000_000:
            return JSONResponse({"detail": "Request too large"}, status_code=413)
        chunks.append(chunk)
    request._body = b"".join(chunks)
    return await call_next(request)


@app.post("/v1/evaluate", dependencies=[Depends(authenticate)])
def evaluation(data: Evaluation):
    return evaluate(data.model_dump())
