from fastapi import FastAPI, Query
from fastapi.middleware.cors import CORSMiddleware
from sentence_transformers import SentenceTransformer
import mysql.connector
import numpy as np
from sklearn.metrics.pairwise import cosine_similarity

app = FastAPI()

# Allow CORS (for frontend to call this API)
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["*"],
    allow_headers=["*"],
)

model = SentenceTransformer('all-MiniLM-L6-v2')  # Light and fast

# DB connection
db = mysql.connector.connect( 
    host="localhost",
    user="root",
    password="",
    database="paper_archive"
)
cursor = db.cursor(dictionary=True)

def fetch_papers():
    cursor.execute("SELECT id, title, subject, year FROM papers")
    papers = cursor.fetchall()
    for paper in papers:
        text = f"{paper['title']} {paper['subject']} {paper['year']}"
        paper['embedding'] = model.encode(text)
    return papers

# Load data into memory with embeddings
papers = fetch_papers()

@app.get("/search")
def search(q: str = Query(...)):
    query_vector = model.encode(q)
    results = []

    for paper in papers:
        sim = cosine_similarity([query_vector], [paper['embedding']])[0][0]
        results.append((sim, paper))

    results.sort(key=lambda x: x[0], reverse=True)
    top = [item[1] for item in results[:5]]

    return {"results": top}
