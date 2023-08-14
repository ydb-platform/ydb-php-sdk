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
	//var spans = map[SpanName]map[int]Span{}
	//spans["read"] = map[int]Span{} /**/
	http.HandleFunc("/prepare", func(writer http.ResponseWriter, request *http.Request) {
		endpoint, err := url.Parse(request.URL.Query().Get("endpoint"))
		if err != nil {
			panic(err)
		}
		version := request.URL.Query().Get("version")
		m, err = New(endpoint.String(), version, "workload-php")
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
		writer.Write([]byte(request.URL.Query().Encode()))
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
		writer.Write([]byte(request.URL.Query().Encode()))
	})
	http.HandleFunc("/done", func(writer http.ResponseWriter, request *http.Request) {
		//println("done", request.URL.Query().Encode())
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
		//delete(spans[job], process)
		writer.Write([]byte(request.URL.Query().Encode()))
	})
	http.HandleFunc("/fail", func(writer http.ResponseWriter, request *http.Request) {
		//println("fail", request.URL.Query().Encode())
		job := request.URL.Query().Get("job")
		process := request.URL.Query().Get("process")
		error := request.URL.Query().Get("error")
		attempts, err := strconv.Atoi(request.URL.Query().Get("attempts"))
		if err != nil {
			panic(err)
		}
		s, ok := spans.Load(job + process)
		if !ok {
			println("Error in done in find span")
		}
		s.(Span).Stop(error, attempts)
		//delete(spans[job], process)
		writer.Write([]byte(request.URL.Query().Encode()))
	})
	err := http.ListenAndServe(":88", nil)
	if err != nil {
		panic(err)
		return
	} else {
		println("Server started")
	}
}

func pushGate(m *Metrics, workTime, pushInterval time.Duration) {
	ctx, cancel := context.WithTimeout(context.Background(), workTime)
	defer cancel()

	ticker := time.NewTicker(pushInterval)

	for {
		select {
		case <-ctx.Done():
			return
		case <-ticker.C:
			m.Push()
		}
	}
}
