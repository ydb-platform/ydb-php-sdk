package main

import "time"

type Span struct {
	name  SpanName
	start time.Time
	m     *Metrics
}

type SpanName = string

const (
	JobRead  SpanName = "read"
	JobWrite SpanName = "write"
)

const (
	JobStatusOK  = "ok"
	JobStatusErr = "err"
)
