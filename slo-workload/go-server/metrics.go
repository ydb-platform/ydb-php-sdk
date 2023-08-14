package main

import (
	"fmt"
	"time"

	"github.com/prometheus/client_golang/prometheus"
	"github.com/prometheus/client_golang/prometheus/push"
)

type (
	Metrics struct {
		oks       *prometheus.GaugeVec
		notOks    *prometheus.GaugeVec
		inflight  *prometheus.GaugeVec
		errors    *prometheus.GaugeVec
		latencies *prometheus.SummaryVec
		stats     *prometheus.GaugeVec
		attempts  *prometheus.HistogramVec

		p *push.Pusher

		label string
	}
)

func New(url, label, jobName, sdk string) (*Metrics, error) {
	m := &Metrics{
		label: label,
	}

	m.oks = prometheus.NewGaugeVec(
		prometheus.GaugeOpts{
			Name: "oks",
			Help: "amount of OK requests",
		},
		[]string{"jobName"},
	)
	m.notOks = prometheus.NewGaugeVec(
		prometheus.GaugeOpts{
			Name: "not_oks",
			Help: "amount of not OK requests",
		},
		[]string{"jobName"},
	)
	m.inflight = prometheus.NewGaugeVec(
		prometheus.GaugeOpts{
			Name: "inflight",
			Help: "amount of requests in flight",
		},
		[]string{"jobName"},
	)
	m.errors = prometheus.NewGaugeVec(
		prometheus.GaugeOpts{
			Name: "errors",
			Help: "error",
		},
		[]string{"jobName", "class"},
	)
	m.latencies = prometheus.NewSummaryVec(
		prometheus.SummaryOpts{
			Name: "latency",
			Help: "summary of latencies in ms",
			Objectives: map[float64]float64{
				0.5:  0,
				0.99: 0,
				1.0:  0,
			},
			MaxAge: 15 * time.Second,
		},
		[]string{"status", "jobName"},
	)
	m.attempts = prometheus.NewHistogramVec(
		prometheus.HistogramOpts{
			Name:    "attempts",
			Help:    "summary of amount for request",
			Buckets: prometheus.LinearBuckets(1, 1, 10),
		},
		[]string{"status", "jobName"},
	)

	m.p = push.New(url, jobName).
		Grouping("sdk", sdk).
		Grouping("sdkVersion", label).
		Collector(m.oks).
		Collector(m.notOks).
		Collector(m.inflight).
		Collector(m.latencies).
		Collector(m.attempts).
		Collector(m.errors)

	return m, m.Reset() //nolint:gocritic
}

func (m *Metrics) Push() error {
	e := m.p.Push()
	return e
}

func (m *Metrics) Reset() error {
	m.oks.WithLabelValues(JobRead).Set(0)
	m.oks.WithLabelValues(JobWrite).Set(0)

	m.notOks.WithLabelValues(JobRead).Set(0)
	m.notOks.WithLabelValues(JobWrite).Set(0)

	m.inflight.WithLabelValues(JobRead).Set(0)
	m.inflight.WithLabelValues(JobWrite).Set(0)

	m.latencies.Reset()

	m.attempts.Reset()

	m.errors.Reset()

	return m.Push()
}

func (m *Metrics) Start(name SpanName) Span {
	j := Span{
		name:  name,
		start: time.Now(),
		m:     m,
	}

	m.inflight.WithLabelValues(name).Add(1)
	//println("start", j.name)
	return j
}

func (j Span) Stop(err string, attempts int) {
	j.m.inflight.WithLabelValues(j.name).Sub(1)

	latency := time.Since(j.start)

	if attempts > 1 {
		fmt.Printf("more than 1 attempt for request (request_type: %q, attempts: %d, start: %s, latency: %s, err: %v)\n",
			j.name,
			attempts,
			j.start.Format(time.DateTime),
			latency.String(),
			err,
		)
	}

	var (
		successLabel   = JobStatusOK
		successCounter = j.m.oks
	)

	if err != "" {
		successLabel = JobStatusErr
		successCounter = j.m.notOks
		j.m.errors.WithLabelValues(j.name, err).Inc()
	}

	j.m.latencies.WithLabelValues(successLabel, j.name).Observe(float64(latency.Milliseconds()))
	j.m.attempts.WithLabelValues(successLabel, j.name).Observe(float64(attempts))
	successCounter.WithLabelValues(j.name).Add(1)
	//println("stop", j.name)
}
