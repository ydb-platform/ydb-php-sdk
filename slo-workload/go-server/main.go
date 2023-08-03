package main

import (
	"net/http"
	"net/url"
	"strconv"
	"os"
	"sync"
	"time"
)

func main() {

	var m *Metrics
	var spans = sync.Map{}
	//var spans = map[SpanName]map[int]Span{}
	//spans["read"] = map[int]Span{} /**/
	http.HandleFunc("/prepare", func(writer http.ResponseWriter, request *http.Request) {
		endpoint, _ := url.Parse(request.URL.Query().Get("endpoint"))
		label := request.URL.Query().Get("label")
		_ = request.URL.Query().Get("version")
		m, _ = New(endpoint.String(), label, "slo")
		interval, _ := strconv.Atoi(request.URL.Query().Get("interval"))
		workTime, _ := strconv.Atoi(request.URL.Query().Get("time"))
		pushInterval := interval * int(time.Millisecond)
		writer.Write([]byte(request.URL.Query().Encode()))
		go pushGate(m, workTime, pushInterval)
	})
	http.HandleFunc("/start", func(writer http.ResponseWriter, request *http.Request) {
		//println("start", request.URL.Query().Encode())
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
		attempts, _ := strconv.Atoi(request.URL.Query().Get("attempts"))
		s, _ := spans.Load(job + process)
		s.(Span).Stop(false, attempts)
		//delete(spans[job], process)
		writer.Write([]byte(request.URL.Query().Encode()))
	})
	http.HandleFunc("/fail", func(writer http.ResponseWriter, request *http.Request) {
		//println("fail", request.URL.Query().Encode())
		job := request.URL.Query().Get("job")
		process := request.URL.Query().Get("process")
		attempts, _ := strconv.Atoi(request.URL.Query().Get("attempts"))
		s, _ := spans.Load(job + process)
		s.(Span).Stop(true, attempts)
		//delete(spans[job], process)
		writer.Write([]byte(request.URL.Query().Encode()))
	})
	err := http.ListenAndServe(":88", nil)
	if err != nil {
		println(err.Error())
		return
	}
}

func pushGate(m *Metrics, workTime int, interval int) {
	timer := time.NewTimer(time.Duration(1e9 * workTime))
	finish := false
	for !finish {
		select {
		case <-timer.C:
			finish = true
			os.Exit(0)
			break
		default:
			time.Sleep(time.Duration(interval))
			m.Push()
		}
	}
}
