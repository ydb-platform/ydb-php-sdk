package main

import (
	"context"
	"net/http"
	"net/url"
	"strconv"
	"sync"
	"time"
)

func main() {

	var m *Metrics
	var spans = sync.Map{}
	http.HandleFunc("/prepare", func(writer http.ResponseWriter, request *http.Request) {
		endpoint, err := url.Parse(request.URL.Query().Get("endpoint"))
		if err != nil {
			panic(err)
		}
		version := request.URL.Query().Get("version")
		m, err = New(endpoint.String(), version, "workload-php", "php")
		if err != nil {
			panic(err)
		}
		interval, err := strconv.Atoi(request.URL.Query().Get("interval"))
		if err != nil {
			panic(err)
		}
		workTime, err := strconv.Atoi(request.URL.Query().Get("time"))
		if err != nil {
			panic(err)
		}
		_, err = writer.Write([]byte(request.URL.Query().Encode()))
		if err != nil {
			println(err)
		}
		go pushGate(m, time.Duration(workTime)*time.Second, time.Duration(interval)*time.Millisecond)
	})
	http.HandleFunc("/start", func(writer http.ResponseWriter, request *http.Request) {
		job := request.URL.Query().Get("job")
		process := request.URL.Query().Get("process")
		var j SpanName
		if request.URL.Query().Get("job") == "read" {
			j = JobRead
		} else {
			j = JobWrite
		}
		spans.Store(job+process, m.Start(j))
		_, err := writer.Write([]byte(request.URL.Query().Encode()))
		if err != nil {
			println(err)
		}
	})
	http.HandleFunc("/done", func(writer http.ResponseWriter, request *http.Request) {
		job := request.URL.Query().Get("job")
		process := request.URL.Query().Get("process")
		attempts, err := strconv.Atoi(request.URL.Query().Get("attempts"))
		if err != nil {
			panic(err)
		}
		s, ok := spans.Load(job + process)
		if !ok {
			println("Error in done in find span")
		}
		s.(Span).Stop("", attempts)
		_, err = writer.Write([]byte(request.URL.Query().Encode()))
		if err != nil {
			println(err)
		}
	})
	http.HandleFunc("/fail", func(writer http.ResponseWriter, request *http.Request) {
		job := request.URL.Query().Get("job")
		process := request.URL.Query().Get("process")
		errorClass := request.URL.Query().Get("error")
		attempts, err := strconv.Atoi(request.URL.Query().Get("attempts"))
		if err != nil {
			panic(err)
		}
		s, ok := spans.Load(job + process)
		if !ok {
			println("Error in done in find span")
		}
		s.(Span).Stop(errorClass, attempts)
		_, err = writer.Write([]byte(request.URL.Query().Encode()))
		if err != nil {
			println(err)
		}
	})
	err := http.ListenAndServe(":88", nil)
	if err != nil {
		panic(err)
		return
	}
}

func pushGate(m *Metrics, workTime, pushInterval time.Duration) {
	ctx, cancel := context.WithTimeout(context.Background(), workTime)
	defer cancel()

	ticker := time.NewTicker(pushInterval)

	for {
		select {
		case <-ctx.Done():
			err := m.Reset()
			if err != nil {
				return
			}
			return
		case <-ticker.C:
			err := m.Push()
			if err != nil {
				println(err)
			}
		}
	}
}
