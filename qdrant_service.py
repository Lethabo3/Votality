import sys
import os
from dotenv import load_dotenv
from qdrant_client import QdrantClient
from qdrant_client.http import models
from sentence_transformers import SentenceTransformer
import json

# Load environment variables
load_dotenv()

# Configuration
QDRANT_URL = os.getenv('QDRANT_URL')
QDRANT_API_KEY = os.getenv('QDRANT_API_KEY')
COLLECTION_NAME = "portfolio_collection"
VECTOR_SIZE = 384  # This should match the output size of your SentenceTransformer model

# Initialize Qdrant client
client = QdrantClient(url=QDRANT_URL, api_key=QDRANT_API_KEY)

# Initialize SentenceTransformer model
model = SentenceTransformer('all-MiniLM-L6-v2')

def create_collection():
    try:
        client.create_collection(
            collection_name=COLLECTION_NAME,
            vectors_config=models.VectorParams(size=VECTOR_SIZE, distance=models.Distance.COSINE)
        )
        print(f"Collection '{COLLECTION_NAME}' created successfully.")
    except Exception as e:
        print(f"Error creating collection: {str(e)}")

def add_document(text):
    try:
        # Generate embedding
        embedding = model.encode(text).tolist()
        
        # Add point to the collection
        client.upsert(
            collection_name=COLLECTION_NAME,
            points=[models.PointStruct(id=hash(text), vector=embedding, payload={"text": text})]
        )
        print(f"Document added successfully: {text[:50]}...")
    except Exception as e:
        print(f"Error adding document: {str(e)}")

def search(query, limit=5):
    try:
        # Generate query embedding
        query_vector = model.encode(query).tolist()
        
        # Perform the search
        search_result = client.search(
            collection_name=COLLECTION_NAME,
            query_vector=query_vector,
            limit=limit
        )
        
        # Extract and return the results
        results = [{"id": hit.id, "score": hit.score, "text": hit.payload["text"]} for hit in search_result]
        print(json.dumps(results))
    except Exception as e:
        print(json.dumps({"error": str(e)}))

def collection_exists():
    try:
        collections = client.get_collections().collections
        exists = any(collection.name == COLLECTION_NAME for collection in collections)
        print(str(exists))
    except Exception as e:
        print(f"Error checking collection existence: {str(e)}")

def get_collection_info():
    try:
        info = client.get_collection(COLLECTION_NAME)
        print(json.dumps({"vectors_count": info.vectors_count, "points_count": info.points_count}))
    except Exception as e:
        print(json.dumps({"error": str(e)}))

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("Usage: python qdrant_service.py <command> [args]")
        sys.exit(1)

    command = sys.argv[1]

    if command == "create_collection":
        create_collection()
    elif command == "add_document":
        if len(sys.argv) < 3:
            print("Usage: python qdrant_service.py add_document <text>")
            sys.exit(1)
        add_document(sys.argv[2])
    elif command == "search":
        if len(sys.argv) < 3:
            print("Usage: python qdrant_service.py search <query>")
            sys.exit(1)
        search(sys.argv[2])
    elif command == "collection_exists":
        collection_exists()
    elif command == "get_collection_info":
        get_collection_info()
    else:
        print(f"Unknown command: {command}")
        sys.exit(1)